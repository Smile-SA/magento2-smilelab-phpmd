<?php

declare(strict_types=1);

namespace SmileLab\CodeMessDetector\Rule\Design;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieReaderInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use PHPMD\AbstractNode;
use PHPMD\AbstractRule;
use PHPMD\Node\ClassNode;
use PHPMD\Rule\ClassAware;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

/**
 * Session and Cookies must be used only in HTML Presentation layer.
 * Smile override: Add observer case.
 *
 * @see https://github.com/magento/magento2/blob/2.4.5/dev/tests/static/framework/Magento/CodeMessDetector/Rule/Design/CookieAndSessionMisuse.php
 */
class CookieAndSessionMisuse extends AbstractRule implements ClassAware
{
    /**
     * Is given class a controller?
     *
     * @param ReflectionClass $class
     * @return bool
     */
    private function isController(ReflectionClass $class): bool
    {
        return $class->isSubclassOf(ActionInterface::class);
    }

    /**
     * Is given class a block?
     *
     * @param ReflectionClass $class
     * @return bool
     */
    private function isBlock(ReflectionClass $class): bool
    {
        return $class->isSubclassOf(BlockInterface::class);
    }

    /**
     * Is given class an HTML UI data provider?
     *
     * @param ReflectionClass $class
     * @return bool
     */
    private function isUiDataProvider(ReflectionClass $class): bool
    {
        return $class->isSubclassOf(DataProviderInterface::class);
    }

    /**
     * Is given class a Layout Processor?
     *
     * @param ReflectionClass $class
     * @return bool
     */
    private function isLayoutProcessor(ReflectionClass $class): bool
    {
        return $class->isSubclassOf(LayoutProcessorInterface::class);
    }

    /**
     * Is given class a View Model?
     *
     * @param ReflectionClass $class
     * @return bool
     */
    private function isViewModel(ReflectionClass $class): bool
    {
        return $class->isSubclassOf(ArgumentInterface::class);
    }

    /**
     * Is given class an observer?
     *
     * @param ReflectionClass $class
     * @return bool
     */
    private function isObserver(ReflectionClass $class): bool
    {
        return $class->isSubclassOf(ObserverInterface::class);
    }

    /**
     * Is given class an HTML UI Document?
     *
     * @param ReflectionClass $class
     * @return bool
     */
    private function isUiDocument(ReflectionClass $class): bool
    {
        return $class->isSubclassOf(Document::class) || $class->getName() === Document::class;
    }

    /**
     * Is given class a plugin for controllers?
     *
     * @param ReflectionClass $class
     * @return bool
     */
    private function isControllerPlugin(ReflectionClass $class): bool
    {
        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (preg_match('/^(after|around|before).+/i', $method->getName())) {
                try {
                    $parameters = $method->getParameters();
                    if (count($parameters) === 0) {
                        continue;
                    }
                    $argument = $this->getParameterClass($parameters[0]);
                } catch (Throwable $exception) {
                    // Non-existing class (autogenerated perhaps) or doesn't have an argument
                    continue;
                }
                if ($argument) {
                    $isAction = $argument->isSubclassOf(ActionInterface::class)
                        || $argument->getName() === ActionInterface::class;
                    if ($isAction) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Is given class a plugin for blocks?
     *
     * @param ReflectionClass $class
     * @return bool
     */
    private function isBlockPlugin(ReflectionClass $class): bool
    {
        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (preg_match('/^(after|around|before).+/i', $method->getName())) {
                try {
                    $parameters = $method->getParameters();
                    if (count($parameters) === 0) {
                        continue;
                    }
                    $argument = $this->getParameterClass($parameters[0]);
                } catch (Throwable $exception) {
                    // Non-existing class (autogenerated perhaps) or doesn't have an argument
                    continue;
                }
                if ($argument) {
                    $isBlock = $argument->isSubclassOf(BlockInterface::class)
                        || $argument->getName() === BlockInterface::class;
                    if ($isBlock) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Whether given class depends on classes to pay attention to.
     *
     * @param ReflectionClass $class
     * @return bool
     */
    private function doesUseRestrictedClasses(ReflectionClass $class): bool
    {
        $constructor = $class->getConstructor();
        if ($constructor) {
            foreach ($constructor->getParameters() as $argument) {
                try {
                    $class = $this->getParameterClass($argument);
                    if ($class === null) {
                        continue;
                    }
                    if (
                        $class->isSubclassOf(SessionManagerInterface::class)
                        || $class->getName() === SessionManagerInterface::class
                        || $class->isSubclassOf(CookieReaderInterface::class)
                        || $class->getName() === CookieReaderInterface::class
                    ) {
                        return true;
                    }
                } catch (ReflectionException $exception) {
                    // Failed to load the argument's class information
                    continue;
                }
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     *
     * @param ClassNode $node
     */
    public function apply(AbstractNode $node): void
    {
        try {
            $class = new ReflectionClass($node->getFullQualifiedName());
        } catch (Throwable $exception) {
            // Failed to load class, nothing we can do
            return;
        }

        if ($this->doesUseRestrictedClasses($class)) {
            if (
                !$this->isController($class)
                && !$this->isBlock($class)
                && !$this->isUiDataProvider($class)
                && !$this->isUiDocument($class)
                && !$this->isControllerPlugin($class)
                && !$this->isBlockPlugin($class)
                && !$this->isLayoutProcessor($class)
                && !$this->isViewModel($class)
                && !$this->isObserver($class)
            ) {
                $this->addViolation($node, [$node->getFullQualifiedName()]);
            }
        }
    }

    /**
     * Get class by reflection parameter
     *
     * @param ReflectionParameter $reflectionParameter
     *
     * @return ReflectionClass|null
     * @throws ReflectionException
     */
    private function getParameterClass(ReflectionParameter $reflectionParameter): ?ReflectionClass
    {
        $parameterType = $reflectionParameter->getType();
        // In PHP8, $parameterType could be an instance of ReflectionUnionType, which doesn't have isBuiltin method.
        if (!$parameterType instanceof ReflectionNamedType) {
            return null;
        }

        return !$parameterType->isBuiltin()
            ? new ReflectionClass($parameterType->getName())
            : null;
    }
}
