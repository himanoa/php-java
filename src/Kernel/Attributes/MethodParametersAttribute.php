<?php
namespace PHPJava\Kernel\Attributes;

use PHPJava\Exceptions\NotImplementedException;
use PHPJava\Kernel\Structures\_LocalVariableTable;
use PHPJava\Utilities\BinaryTool;

final class MethodParametersAttribute implements AttributeInterface
{
    use \PHPJava\Kernel\Core\BinaryReader;
    use \PHPJava\Kernel\Core\ConstantPool;
    use \PHPJava\Kernel\Core\AttributeReference;
    use \PHPJava\Kernel\Core\DebugTool;

    private $parameters = [];

    public function execute(): void
    {
        $parametersCount = $this->readUnsignedByte();
        for ($i = 0; $i < $parametersCount; $i++) {
            $this->parameters[] = [
                'name_index' => $this->readUnsignedShort(),
                'access_flags' => $this->readUnsignedShort(),
            ];
        }
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
