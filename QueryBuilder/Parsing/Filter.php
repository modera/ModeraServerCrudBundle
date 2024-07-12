<?php

namespace Modera\ServerCrudBundle\QueryBuilder\Parsing;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
class Filter implements FilterInterface
{
    public const ARRAY_DELIMITER = ',';

    // supported comparators:
    public const COMPARATOR_EQUAL = 'eq';
    public const COMPARATOR_NOT_EQUAL = 'neq';
    public const COMPARATOR_LIKE = 'like';
    public const COMPARATOR_NOT_LIKE = 'notLike';
    public const COMPARATOR_GREATER_THAN = 'gt';
    public const COMPARATOR_GREATER_THAN_OR_EQUAL = 'gte';
    public const COMPARATOR_LESS_THAN = 'lt';
    public const COMPARATOR_LESS_THAN_OR_EQUAL = 'lte';
    public const COMPARATOR_IN = 'in';
    public const COMPARATOR_NOT_IN = 'notIn';
    public const COMPARATOR_IS_NULL = 'isNull';
    public const COMPARATOR_IS_NOT_NULL = 'isNotNull';

    /**
     * @var array{
     *     'property'?: ?string,
     *     'value'?: string|string[]|null,
     * }
     */
    private array $input;

    /**
     * @var array{
     *     'property'?: ?string,
     *     'comparator'?: ?string,
     *     'value'?: string|string[]|array{
     *         'comparator': string,
     *         'value'?: string|string[]
     *     }[]|null}
     * }
     */
    private array $parsedInput;

    /**
     * @var ?string[]
     */
    private static ?array $supportedComparators = null;

    /**
     * @param array{
     *     'property'?: ?string,
     *     'value'?: string|string[]|null,
     * } $input
     */
    public function __construct(array $input)
    {
        $this->input = $input;
        $this->parsedInput = $this->parse($input);
    }

    /**
     * @return string[]
     */
    public static function getSupportedComparators(): array
    {
        if (null === self::$supportedComparators) {
            /** @var string[] $supportedComparators */
            $supportedComparators = [];

            $refClass = new \ReflectionClass(__CLASS__);
            /**
             * @var string $name
             * @var string $value
             */
            foreach ($refClass->getConstants() as $name => $value) {
                if (\str_starts_with($name, 'COMPARATOR_')) {
                    $supportedComparators[] = $value;
                }
            }

            self::$supportedComparators = $supportedComparators;
        }

        return self::$supportedComparators;
    }

    /**
     * @param bool|float|int|string|null $value Value can be omitted when comparator is COMPARATOR_IS_NULL or COMPARATOR_IS_NOT_NULL
     */
    public static function create(string $property, string $comparator, $value = null): self
    {
        if (\in_array($comparator, [self::COMPARATOR_IS_NULL, self::COMPARATOR_IS_NOT_NULL])) {
            $raw = ['property' => $property, 'value' => $comparator];
        } else {
            $raw = ['property' => $property, 'value' => \sprintf('%s:%s', $comparator, $value)];
        }

        return new self($raw);
    }

    public function isValid(): bool
    {
        $isValid = \is_string($this->parsedInput['property'] ?? null);

        // TODO: make sure that OR-ed "comparators" are also valid
        $isValid = $isValid && (
            (null === ($this->parsedInput['comparator'] ?? null) && \is_array($this->parsedInput['value'] ?? null))
            || (\is_string($this->parsedInput['comparator'] ?? null) && \in_array($this->parsedInput['comparator'], self::getSupportedComparators()))
        );

        return $isValid;
    }

    /**
     * @return array{
     *     'comparator': string,
     *     'value'?: string|string[],
     * }
     */
    private function parseStringFilterValue(string $value): array
    {
        $parsed = [];

        /** @var int|false $separatorPosition */
        $separatorPosition = \strpos($value, ':');
        if (false === $separatorPosition) {
            $separatorPosition = null;
        }

        if (null === $separatorPosition && \in_array($value, [self::COMPARATOR_IS_NULL, self::COMPARATOR_IS_NOT_NULL])) {
            $parsed['comparator'] = $value;
        } else {
            /** @var string $comparator */
            $comparator = null === $separatorPosition ? $value : \substr($value, 0, $separatorPosition);
            $parsed['comparator'] = $comparator;

            /** @var string $value */
            $value = null === $separatorPosition ? '' : \substr($value, $separatorPosition + 1);
            $parsed['value'] = $value;

            if (\in_array($parsed['comparator'], [self::COMPARATOR_IN, self::COMPARATOR_NOT_IN])) {
                /** @var string[] $value */
                $value = '' != $parsed['value'] ? \explode(self::ARRAY_DELIMITER, $parsed['value']) : [];
                $parsed['value'] = $value;
            }

            if ('' === $parsed['value']) {
                unset($parsed['value']);
            }
        }

        return $parsed;
    }

    /**
     * @param array{
     *     'property'?: ?string,
     *     'value'?: string|string[]|null,
     * } $input
     *
     * @return array{
     *     'property': ?string,
     *     'comparator': ?string,
     *     'value': string|string[]|array{
     *         'comparator': string,
     *         'value'?: string|string[]
     *     }[]|null},
     */
    private function parse(array $input): array
    {
        $parsed = [
            'property' => null,
            'comparator' => null,
            'value' => null,
        ];

        if (isset($input['property'])) {
            $parsed['property'] = $input['property'];
        }

        if (isset($input['value'])) {
            if (\is_string($input['value'])) {
                /** @var array{'property': ?string, 'comparator': ?string, 'value': string|string[]|null} $parsed */
                $parsed = \array_merge($parsed, $this->parseStringFilterValue($input['value']));
            } elseif (\is_array($input['value'])) {
                $parsed['value'] = [];
                foreach ($input['value'] as $value) {
                    /** @var array{'comparator': string, 'value'?: string|string[]} $parsedValue */
                    $parsedValue = $this->parseStringFilterValue($value);
                    $parsed['value'][] = $parsedValue;
                }
            }
        }

        return $parsed;
    }

    /**
     * @return array{'property': string, 'value': string}
     */
    public function compile(): array
    {
        if (\is_string($this->parsedInput['comparator'] ?? null) && \in_array($this->parsedInput['comparator'], [self::COMPARATOR_IS_NULL, self::COMPARATOR_IS_NOT_NULL])) {
            $value = $this->parsedInput['comparator'];
        } else {
            /** @var string|string[] $parsedValue */
            $parsedValue = $this->parsedInput['value'] ?? null;
            if (\is_array($this->parsedInput['value'] ?? null)) {
                /** @var string[] $rawValue */
                $rawValue = $this->parsedInput['value'];
                $parsedValue = \implode(self::ARRAY_DELIMITER, $rawValue);
            }
            /** @var string $parsedValue */
            $parsedValue = $parsedValue;
            $value = ($this->parsedInput['comparator'] ?? '').':'.$parsedValue;
        }

        return [
            'property' => $this->parsedInput['property'] ?? '',
            'value' => $value,
        ];
    }

    public function getProperty(): ?string
    {
        return $this->parsedInput['property'] ?? null;
    }

    public function setProperty(string $property): self
    {
        $this->parsedInput['property'] = $property;

        return $this;
    }

    /**
     * @return string|string[]|array{
     *     'comparator': string,
     *     'value'?: string|string[]
     * }[]|null
     */
    public function getValue()
    {
        return $this->parsedInput['value'] ?? null;
    }

    public function setValue(string $value): self
    {
        $this->parsedInput['value'] = $value;

        return $this;
    }

    public function getComparator(): ?string
    {
        return $this->parsedInput['comparator'] ?? null;
    }

    public function setComparator(string $comparator): self
    {
        $this->parsedInput['comparator'] = $comparator;

        return $this;
    }

    /**
     * @return array{
     *     'property'?: ?string,
     *     'value'?: string|string[]|null,
     * }
     */
    public function getInput(): array
    {
        return $this->input;
    }
}
