<?php

declare(strict_types=1);

namespace Iva\Help;

use Iva\Command\CommandNode;
use Iva\Command\CommandTree;
use Iva\Input\Argument;
use Iva\Input\Definition;
use Iva\Input\GlobalOptions;
use Iva\Input\Option;
use Iva\Input\OptionMode;
use Iva\Input\OptionOrigin;
use Iva\Output\ConsoleOutput;
use Iva\Output\ErrorConsoleOutput;
use Iva\Output\Output;
use Iva\Output\Terminal\TerminalInfo;

/**
 * Renders `--help`. Every list (arguments, options, commands) is a two-column
 * table: a label column and a description column. Descriptions are wrapped to
 * the terminal width and every continuation line is indented to the start of
 * the description column, so the text always forms one straight block to the
 * right of the labels; a blank line separates the items.
 */
final readonly class HelpGenerator
{
    private const int DEFAULT_COLUMNS = 80;
    private const int MIN_COLUMNS = 40;
    private const int MAX_COLUMNS = 120;
    private const int MIN_DESCRIPTION_WIDTH = 20;

    /**
     * @param int|null $width columns to wrap at; null follows the terminal
     *        (COLUMNS, 80 when unknown or when the output is not a console)
     */
    public function __construct(
        private UsageLineBuilder $usageLineBuilder = new UsageLineBuilder(),
        private ?int $width = null,
        private TextWrapper $wrapper = new TextWrapper(),
    ) {}

    public function renderApplicationRoot(
        Output $output,
        string $appName,
        string $appVersion,
        CommandTree $tree,
        ?Definition $globals = null,
    ): void {
        $output->writeln(sprintf('%s %s', $appName, $appVersion));
        $output->writeln('');
        $output->writeln('Usage:');
        $output->writeln(sprintf('  %s [options] <command> [arguments]', $appName));

        $this->renderCommandGroups($output, $tree->roots());

        if ($globals !== null) {
            $this->renderOptionBlock($output, 'Global options:', $globals->optionsWithOrigin(OptionOrigin::Global));
        }
    }

    public function renderGroup(Output $output, string $appName, CommandNode $node, ?Definition $definition = null): void
    {
        $metadata = $node->metadata();

        if ($metadata->description !== '') {
            $output->writeln($metadata->description);
            $output->writeln('');
        }

        $output->writeln('Usage:');
        $output->writeln(sprintf('  %s %s [options] <command>', $appName, $node->path()));

        $this->renderCommandGroups($output, $node->children(), 'Available subcommands:');

        if ($definition !== null) {
            $this->renderInheritedAndGlobal($output, $definition);
        }

        $this->renderExamples($output, $node);
    }

    public function renderCommand(Output $output, string $appName, CommandNode $node, Definition $definition): void
    {
        $metadata = $node->metadata();

        if ($metadata->description !== '') {
            $output->writeln($metadata->description);
            $output->writeln('');
        }

        $output->writeln('Usage:');
        $output->writeln('  ' . $this->usageLineBuilder->build($appName, $node, $definition));

        if ($metadata->aliases !== []) {
            $output->writeln('');
            $output->writeln(sprintf('Aliases: %s', implode(', ', $metadata->aliases)));
        }

        $this->renderArguments($output, $definition->arguments());
        $this->renderOptionBlock($output, 'Options:', $definition->optionsWithOrigin(OptionOrigin::Local));

        if ($node->hasChildren()) {
            $this->renderCommandGroups($output, $node->children(), 'Subcommands:');
        }

        $this->renderInheritedAndGlobal($output, $definition);
        $this->renderExamples($output, $node);
    }

    private function renderInheritedAndGlobal(Output $output, Definition $definition): void
    {
        $this->renderOptionBlock(
            $output,
            'Inherited options:',
            $definition->optionsWithOrigin(OptionOrigin::Inherited),
        );

        $this->renderOptionBlock(
            $output,
            'Global options:',
            $definition->optionsWithOrigin(OptionOrigin::Global),
        );
    }

    /**
     * @param list<Argument> $arguments
     */
    private function renderArguments(Output $output, array $arguments): void
    {
        if ($arguments === []) {
            return;
        }

        $rows = [];

        foreach ($arguments as $argument) {
            $notes = [];

            if ($argument->default !== null && $argument->default !== []) {
                $notes[] = sprintf('default: %s', $this->formatDefault($argument->default));
            }

            $rows[] = [
                'label' => $argument->variadic ? $argument->name . '...' : $argument->name,
                'description' => $argument->description,
                'notes' => $notes,
            ];
        }

        $output->writeln('');
        $output->writeln('Arguments:');

        $this->renderRows($output, $rows);
    }

    /**
     * @param list<Option> $options
     */
    private function renderOptionBlock(Output $output, string $heading, array $options): void
    {
        if ($options === []) {
            return;
        }

        usort($options, static fn(Option $a, Option $b): int => $a->name <=> $b->name);

        $rows = [];

        foreach ($options as $option) {
            $notes = [];

            if ($option->default !== null && $option->default !== false && $option->default !== []) {
                $notes[] = sprintf('default: %s', $this->formatDefault($option->default));
            }

            if ($option->mode === OptionMode::ArrayValue) {
                $notes[] = 'repeatable';
            }

            if ($option->overridesInherited) {
                $notes[] = 'overrides the inherited option';
            }

            $rows[] = [
                'label' => $this->optionLabel($option),
                'description' => $option->description,
                'notes' => $notes,
            ];
        }

        $output->writeln('');
        $output->writeln($heading);

        $this->renderRows($output, $rows);
    }

    private function optionLabel(Option $option): string
    {
        $label = $option->shortcut !== null
            ? sprintf('-%s, --%s', $option->shortcut, $option->name)
            : sprintf('    --%s', $option->name);

        if ($option->acceptsValue()) {
            $label .= '=' . $option->displayValueName();
        } elseif ($option->negatable && !in_array($option->name, GlobalOptions::names(), true)) {
            $label .= sprintf(' / --no-%s', $option->name);
        }

        return $label;
    }

    /**
     * @param list<CommandNode> $nodes
     */
    private function renderCommandGroups(Output $output, array $nodes, string $heading = 'Commands:'): void
    {
        $visible = array_values(array_filter(
            $nodes,
            static fn(CommandNode $node): bool => !$node->metadata()->hidden,
        ));

        if ($visible === []) {
            return;
        }

        /** @var array<string, list<CommandNode>> $groups */
        $groups = [];

        foreach ($visible as $node) {
            $groups[$node->metadata()->group ?? ''][] = $node;
        }

        ksort($groups);

        $labels = [];

        foreach ($visible as $node) {
            $labels[$node->metadata()->name] = $node->metadata()->name;
        }

        // One label column for every group, so descriptions line up across them.
        $width = $this->columnWidth($labels);
        $first = true;

        foreach ($groups as $group => $members) {
            usort($members, static fn(CommandNode $a, CommandNode $b): int => $a->metadata()->name <=> $b->metadata()->name);

            $output->writeln('');
            $output->writeln($group === '' ? ($first ? $heading : 'Other:') : $group . ':');
            $first = false;

            $rows = [];

            foreach ($members as $node) {
                $rows[] = [
                    'label' => $node->metadata()->name,
                    'description' => $node->metadata()->description,
                    'notes' => [],
                ];
            }

            $this->renderRows($output, $rows, $width);
        }
    }

    /**
     * Prints a label/description table.
     *
     *  - the description column starts at a fixed offset, and every wrapped
     *    line of a description is indented to it;
     *  - a label too long for the label column gets a line of its own, with
     *    the description starting below it, still in the description column;
     *  - items are separated by one blank line (none before the first or
     *    after the last);
     *  - notes ("default: x", "repeatable", ...) follow the description as a
     *    single bracketed unit, which is kept on one line when it fits.
     *
     * @param list<array{label: string, description: string, notes: list<string>}> $rows
     * @param int|null $labelWidth label column width; null sizes it to the rows
     */
    private function renderRows(Output $output, array $rows, ?int $labelWidth = null): void
    {
        if ($rows === []) {
            return;
        }

        $labelWidth ??= $this->columnWidth(array_column($rows, 'label'));
        $descriptionIndent = 2 + $labelWidth + 2;
        $descriptionWidth = max(self::MIN_DESCRIPTION_WIDTH, $this->columns($output) - $descriptionIndent);
        $indent = str_repeat(' ', $descriptionIndent);

        foreach ($rows as $position => $row) {
            if ($position > 0) {
                $output->writeln('');
            }

            $text = $this->withNotes($row['description'], $row['notes'], $descriptionWidth);
            $lines = $text === '' ? [] : $this->wrapper->wrap($text, $descriptionWidth);
            $labelVisible = $this->wrapper->width($row['label']);

            if ($lines === []) {
                $output->writeln('  ' . $row['label']);

                continue;
            }

            if ($labelVisible > $labelWidth) {
                $output->writeln('  ' . $row['label']);
            } else {
                $first = array_shift($lines);
                $output->writeln(rtrim(
                    '  ' . $row['label'] . str_repeat(' ', $labelWidth - $labelVisible) . '  ' . $first,
                ));
            }

            foreach ($lines as $line) {
                $output->writeln($line === '' ? '' : $indent . $line);
            }
        }
    }

    /**
     * @param list<string> $notes
     */
    private function withNotes(string $description, array $notes, int $descriptionWidth): string
    {
        if ($notes === []) {
            return $description;
        }

        $note = sprintf('[%s]', implode('; ', $notes));

        if ($this->wrapper->width($note) <= $descriptionWidth) {
            $note = str_replace(' ', TextWrapper::NO_BREAK_SPACE, $note);
        }

        return $description === '' ? $note : $description . ' ' . $note;
    }

    private function columns(Output $output): int
    {
        if ($this->width !== null) {
            return max(self::MIN_COLUMNS, $this->width);
        }

        if (!$output instanceof ConsoleOutput && !$output instanceof ErrorConsoleOutput) {
            return self::DEFAULT_COLUMNS;
        }

        $stream = defined('STDOUT') ? STDOUT : fopen('php://stdout', 'wb');
        $columns = TerminalInfo::detect($stream)->columns;

        return min(self::MAX_COLUMNS, max(self::MIN_COLUMNS, $columns));
    }

    private function renderExamples(Output $output, CommandNode $node): void
    {
        $examples = $node->command()->examples();

        if ($examples === []) {
            return;
        }

        $output->writeln('');
        $output->writeln('Examples:');

        foreach ($examples as $example) {
            if ($example->description !== '') {
                $output->writeln(sprintf('  # %s', $example->description));
            }

            $output->writeln(sprintf('  %s', $example->command));
        }
    }

    /**
     * @param array<array-key, string> $labels
     */
    private function columnWidth(array $labels): int
    {
        $width = 0;

        foreach ($labels as $label) {
            $width = max($width, $this->wrapper->width($label));
        }

        return min(max($width, 8), 32);
    }

    private function formatDefault(mixed $default): string
    {
        if ($default instanceof \BackedEnum) {
            return (string) $default->value;
        }

        if (is_array($default)) {
            return implode(', ', array_map(
                fn(mixed $item): string => $this->formatDefault($item),
                $default,
            ));
        }

        if (is_bool($default)) {
            return $default ? 'true' : 'false';
        }

        return (string) (is_scalar($default) ? $default : '');
    }
}
