<?php
namespace PHPJava\Kernel\Attributes;

use PHPJava\Exceptions\NotImplementedException;
use PHPJava\Kernel\Structures\_ParameterAnnotation;
use PHPJava\Utilities\BinaryTool;

final class RuntimeInvisibleParameterAnnotationsAttribute implements AttributeInterface
{
    use \PHPJava\Kernel\Core\BinaryReader;
    use \PHPJava\Kernel\Core\ConstantPool;
    use \PHPJava\Kernel\Core\AttributeReference;
    use \PHPJava\Kernel\Core\DebugTool;

    private $numParameters;
    private $annotations = [];

    public function execute(): void
    {
        $this->numParameters = $this->readUnsignedByte();

        for ($i = 0; $i < $this->numParameters; $i++) {
            $annotation = new _ParameterAnnotation($this->reader);
            $annotation->setConstantPool($this->getConstantPool());
            $annotation->setDebugTool($this->getDebugTool());
            $annotation->execute();
            $this->annotations[] = $annotation;
        }
    }
}
