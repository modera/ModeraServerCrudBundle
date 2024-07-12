<?php

namespace Modera\ServerCrudBundle\DataMapping;

use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
interface ComplexFieldValueConverterInterface
{
    /**
     * @param mixed $value Mixed value
     */
    public function isResponsible($value, string $fieldName, ClassMetadataInfo $meta): bool;

    /**
     * @param mixed $value Mixed value
     *
     * @return mixed Mixed value
     */
    public function convert($value, string $fieldName, ClassMetadataInfo $meta);
}
