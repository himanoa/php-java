<?php
namespace PHPJava\Kernel\Mnemonics;

use PHPJava\Exceptions\NotImplementedException;
use PHPJava\Utilities\BinaryTool;

final class _ifnonnull implements OperationInterface
{
    use \PHPJava\Kernel\Core\Accumulator;
    use \PHPJava\Kernel\Core\ConstantPool;

    public function execute(): void
    {
        $offset = $this->readShort();
        $operand = $this->popFromOperandStack();

        if ($operand !== null) {
            $this->setOffset($this->getProgramCounter() + $offset);
        }
    }
}
