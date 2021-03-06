<?php
namespace PHPJava\Core;

use PHPJava\Core\JVM\ActiveAttributes;
use PHPJava\Core\JVM\ActiveFields;
use PHPJava\Core\JVM\ActiveInterface;
use PHPJava\Core\JVM\ActiveMethods;
use PHPJava\Core\JVM\ConstantPool;
use PHPJava\Core\JVM\Parameters\GlobalOptions;
use PHPJava\Core\JVM\Parameters\Runtime;
use PHPJava\Core\JVM\Validations\MagicByte;
use PHPJava\Core\Stream\Reader\ReaderInterface;
use PHPJava\Exceptions\DebugTraceIsDisabledException;
use PHPJava\Exceptions\ValidatorException;
use PHPJava\Kernel\Attributes\AttributeInterface;
use PHPJava\Kernel\Attributes\InnerClassesAttribute;
use PHPJava\Kernel\Maps\FieldAccessFlag;
use PHPJava\Kernel\Structures\_Utf8;
use PHPJava\Utilities\ClassResolver;
use PHPJava\Utilities\DebugTool;
use PHPJava\Utilities\Formatter;
use PHPJava\Utilities\SDKVersionResolver;

class JavaClass implements JavaClassInterface
{
    use \PHPJava\Kernel\Core\ConstantPool;

    private $versions = [
        'minor' => null,
        'major' => null,
    ];

    /**
     * @var ConstantPool
     */
    private $constantPool;

    /**
     * @var ActiveInterface
     */
    private $activeInterfaces;

    /**
     * @var ActiveFields
     */
    private $activeFields;

    /**
     * @var ActiveMethods
     */
    private $activeMethods;

    /**
     * @var ActiveAttributes
     */
    private $activeAttributes;

    private $accessFlag = 0;
    private $thisClass = 0;
    private $superClassIndex = 0;

    /**
     * @var _Utf8|null
     */
    private $className = null;

    private $debugTraces = [];

    /**
     * @var JavaClassInvoker
     */
    private $invoker;

    private $innerClasses = [];

    private $parentClass;

    private $superClass;

    private $options = [];

    private $debugTool;

    private $startTime = 0.0;

    /**
     * JavaClass constructor.
     * @param Stream\Reader\ $reader
     * @param array $options
     * @throws ValidatorException
     * @throws \PHPJava\Exceptions\ReadEntryException
     * @throws \PHPJava\Packages\java\lang\ClassNotFoundException
     */
    public function __construct(ReaderInterface $reader, array $options = [])
    {
        $this->startTime = microtime(true);

        // Validate Java file
        if (!(new MagicByte($reader->getBinaryReader()->readUnsignedInt()))->isValid()) {
            throw new ValidatorException($reader . ' has broken or not Java class.');
        }

        // options
        $this->options = $options;

        if (!(($this->options['class_resolver'] ?? null) instanceof ClassResolver)) {
            $this->options['class_resolver'] = new ClassResolver(
                $this->options
            );
        }

        $this->options['class_resolver']->add([
            [ClassResolver::RESOURCE_TYPE_FILE, dirname($reader->getFileName())],
            [ClassResolver::RESOURCE_TYPE_FILE, getcwd()]
        ]);

        // Debug tool
        $this->debugTool = new DebugTool(
            $reader->getJavaPathName(),
            $options
        );

        $this->debugTool->getLogger()->info('Start class emulation');

        // read minor version
        $this->versions['minor'] = $reader->getBinaryReader()->readUnsignedShort();

        $this->debugTool->getLogger()->info('Minor version: ' . $this->versions['minor']);

        // read major version
        $this->versions['major'] = $reader->getBinaryReader()->readUnsignedShort();

        $this->debugTool->getLogger()->info('Major version: ' . $this->versions['major']);

        $this->debugTool->getLogger()->info('JDK version: ' . SDKVersionResolver::resolve($this->versions['major'] . '.' . $this->versions['minor']));

        // read constant pool size
        $this->constantPool = new ConstantPool(
            $reader,
            $reader->getBinaryReader()->readUnsignedShort()
        );

        $constantPoolEntries = $this->constantPool->getEntries();

        $this->debugTool->getLogger()->info('Constant Pools: ' . count($constantPoolEntries));

        // read access flag
        $this->accessFlag = $reader->getBinaryReader()->readUnsignedShort();

        // read this class
        $this->thisClass = $reader->getBinaryReader()->readUnsignedShort();

        $this->className = $constantPoolEntries[$constantPoolEntries[$this->thisClass]->getClassIndex()];

        // read super class
        $this->superClassIndex = $reader->getBinaryReader()->readUnsignedShort();

        $cpInfo = $this->getConstantPool();
        [$resolvedType, $superClass] = $this->options['class_resolver']->resolve(
            $cpInfo[$cpInfo[$this->superClassIndex]->getClassIndex()]->getString(),
            $this
        );

        switch ($resolvedType) {
            case ClassResolver::RESOLVED_TYPE_PACKAGES:
                $this->superClass = new $superClass();
                break;
            default:
                $this->superClass = $superClass;
                break;
        }

        // read interfaces
        $this->activeInterfaces = new ActiveInterface(
            $reader,
            $reader->getBinaryReader()->readUnsignedShort(),
            $this->constantPool,
            $this->debugTool
        );

        $this->debugTool->getLogger()->info('Extracted interfaces: ' . count($this->activeInterfaces->getEntries()));

        // read fields
        $this->activeFields = new ActiveFields(
            $reader,
            $reader->getBinaryReader()->readUnsignedShort(),
            $this->constantPool,
            $this->debugTool
        );

        $this->debugTool->getLogger()->info('Extracted fields: ' . count($this->activeFields->getEntries()));

        // read methods
        $this->activeMethods = new ActiveMethods(
            $reader,
            $reader->getBinaryReader()->readUnsignedShort(),
            $this->constantPool,
            $this->debugTool
        );

        $this->debugTool->getLogger()->info('Extracted methods: ' . count($this->activeMethods->getEntries()));

        // read Attributes
        $this->activeAttributes = new ActiveAttributes(
            $reader,
            $reader->getBinaryReader()->readUnsignedShort(),
            $this->constantPool,
            $this->debugTool
        );

        $this->debugTool->getLogger()->info('Extracted attributes: ' . count($this->activeAttributes->getEntries()));

        foreach ($this->activeAttributes->getEntries() as $entry) {
            if ($entry->getAttributeData() instanceof InnerClassesAttribute) {
                $this->innerClasses = array_merge(
                    $this->innerClasses,
                    $entry->getAttributeData()->getClasses()
                );
            }
        }
        $this->debugTool->getLogger()->info('End of Class');

        $this->invoker = new JavaClassInvoker(
            $this,
            $options
        );

        if ($this->invoker->getStatic()->getMethods()->has('<clinit>')) {
            $this->invoker
                ->getStatic()
                ->getMethods()
                ->call(
                    '<clinit>'
                );
        }
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function __destruct()
    {
        $this->debugTool->getLogger()->info(
            'Spent time: ' . (microtime(true) - $this->startTime) . ' sec.'
        );
    }

    public function getClassName(bool $shortName = false): string
    {
        if ($shortName === true) {
            $split = explode('$', $this->className->getString());
            return $split[count($split) - 1];
        }
        return $this->className->getString();
    }

    public function getInnerClasses(): array
    {
        return $this->innerClasses;
    }

    public function getFields(): array
    {
        return $this->activeFields->getEntries();
    }

    public function getMethods(): array
    {
        return $this->activeMethods->getEntries();
    }

    public function getInvoker(): JavaClassInvoker
    {
        return $this->invoker;
    }

    public function appendDebug($log)
    {
        $this->debugTraces[] = $log;
        return $this;
    }

    public function hasParentClass(): bool
    {
        return isset($this->parentClass);
    }

    public function setParentClass(JavaClass $class): self
    {
        $this->parentClass = $class;
        return $this;
    }

    public function getParentClass(): JavaClass
    {
        return $this->parentClass;
    }

    public function getSuperClass()
    {
        return $this->superClass;
    }

    public function debug(): void
    {
        $isEnabledTrace = $this->options['operations']['enable_trace'] ?? GlobalOptions::get('operations.enable_trace') ?? Runtime::OPERATIONS_ENABLE_TRACE;
        if (!$isEnabledTrace) {
            throw new DebugTraceIsDisabledException(
                "Debug trace is disabled. If you want to show debug trace then enable to `enable_trace` option."
            );
        }
        $cpInfo = $this->getConstantPool();
        foreach ($this->debugTraces as $debugTraces) {
            printf("[method]\n");
            printf(Formatter::beatifyMethodFromConstantPool($debugTraces['method'], $this->getConstantPool()) . "\n");
            printf("\n");
            printf("[code]\n");

            $codeCounter = 0;
            printf(
                "%s\n",
                implode(
                    "\n",
                    array_map(
                        function ($codes) use (&$codeCounter, &$debugTraces) {
                            return implode(
                                ' ',
                                array_map(
                                    function ($code) use (&$codeCounter, &$debugTraces) {
                                        $isMnemonic = in_array($codeCounter, $debugTraces['mnemonic_indexes']);
                                        $codeCounter++;
                                        return ($isMnemonic ? "\e[1m\e[35m" : "") . "<0x{$code}>" . ($isMnemonic ? "\e[m" : "");
                                    },
                                    $codes
                                )
                            );
                        },
                        array_chunk(str_split(bin2hex($debugTraces['raw_code']), 2), 20)
                    )
                )
            );
            printf("\n");
            printf("[executed]\n");

            printf(
                "% 8s | %-6.6s | %-20.20s | %-10.10s | %-15.15s\n",
                "PC",
                "OPCODE",
                "MNEMONIC",
                "OPERANDS",
                "LOCAL STORAGE"
            );

            $line = sprintf(
                "%8s+%8s+%22s+%12s+%17s\n",
                "---------",
                "--------",
                "----------------------",
                "------------",
                "-----------------"
            );

            printf($line);

            foreach ($debugTraces['executed'] as [$opcode, $mnemonic, $localStorage, $stacks, $pointer]) {
                printf(
                    "% 8s | 0x%02X   | %-20.20s | %-10.10s | %-15.15s\n",
                    (int) $pointer,
                    $opcode,
                    // Remove prefix
                    ltrim($mnemonic, '_'),
                    count($stacks),
                    count($localStorage)
                );
            }

            printf($line);
            printf("\n");
        }
    }
}
