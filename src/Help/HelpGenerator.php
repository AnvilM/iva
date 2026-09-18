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
use Iva\Output\Output;

final readonly class HelpGenerator
{
    public function __construct(
        private UsageLineBuilder $usageLineBuilder = new UsageLineBuilder(),
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

        $labels = [];

        foreach ($arguments as $argument) {
            $labels[$argument->name] = $argument->variadic ? $argument->name . '...' : $argument->name;
        }

        $width = $this->columnWidth($labels);

        $output->writeln('');
        $output->writeln('Arguments:');

        foreach ($arguments as $argument) {
            $suffix = $argument->default !== null && $argument->default !== []
                ? sprintf(' [default: %s]', $this->formatDefault($argument->default))
                : '';

            $output->writeln(sprintf(
                '  %-' . $width . 's  %s%s',
                $labels[$argument->name],
                $argument->description,
                $suffix,
            ));
        }
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

        $labels = [];

        foreach ($options as $option) {
            $labels[$option->name] = $this->optionLabel($option);
        }

        $width = $this->columnWidth($labels);

        $output->writeln('');
        $output->writeln($heading);

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

            $output->writeln(sprintf(
                '  %-' . $width . 's  %s%s',
                $labels[$option->name],
                $option->description,
                $notes === [] ? '' : sprintf(' [%s]', implode('; ', $notes)),
            ));
        }
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

        $width = $this->columnWidth($labels);
        $first = true;

        foreach ($groups as $group => $members) {
            usort($members, static fn(CommandNode $a, CommandNode $b): int => $a->metadata()->name <=> $b->metadata()->name);

            $output->writeln('');
            $output->writeln($group === '' ? ($first ? $heading : 'Other:') : $group . ':');
            $first = false;

            foreach ($members as $node) {
                $output->writeln(sprintf(
                    '  %-' . $width . 's  %s',
                    $node->metadata()->name,
                    $node->metadata()->description,
                ));
            }
        }
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
     * @param array<string, string> $labels
     */
    private function columnWidth(array $labels): int
    {
        $width = 0;

        foreach ($labels as $label) {
            $width = max($width, strlen($label));
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
