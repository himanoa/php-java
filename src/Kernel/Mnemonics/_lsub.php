<?php
namespace PHPJava\Kernel\Mnemonics;

use PHPJava\Exceptions\NotImplementedException;
use PHPJava\Kernel\Types\_Long;
use PHPJava\Utilities\BinaryTool;
use PHPJava\Utilities\Extractor;

final class _lsub implements OperationInterface
{
    use \PHPJava\Kernel\Core\Accumulator;
    use \PHPJava\Kernel\Core\ConstantPool;

    public function execute(): void
    {
        $value2 = $this->popFromOperandStack();
        $value1 = $this->popFromOperandStack();

        $this->pushToOperandStack(
            new _Long(
                BinaryTool::sub(
                    Extractor::realValue($value1),
                    Extractor::realValue($value2)
                )
            )
        );
    }
}
