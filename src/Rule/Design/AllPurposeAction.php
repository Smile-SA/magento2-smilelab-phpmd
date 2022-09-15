<?php

declare(strict_types=1);

namespace Smile\CodeMessDetector\Rule\Design;

use Magento\Framework\App\ActionInterface;
use PHPMD\AbstractNode;
use PHPMD\AbstractRule;
use PHPMD\Node\ClassNode;
use PHPMD\Rule\ClassAware;
use Throwable;

/**
 * Actions must process a defined list of HTTP methods.
 *
 * @see https://github.com/magento/magento2/blob/2.4.5/dev/tests/static/framework/Magento/CodeMessDetector/Rule/Design/AllPurposeAction.php
 */
class AllPurposeAction extends AbstractRule implements ClassAware
{
    /**
     * @inheritdoc
     * @param ClassNode $node
     */
    public function apply(AbstractNode $node)
    {
        // Skip validation for Abstract Controllers
        if ($node->isAbstract()) {
            return;
        }
        try {
            if (!class_exists($node->getFullQualifiedName(), true)) {
                return;
            }
            $impl = class_implements($node->getFullQualifiedName(), true);
        } catch (Throwable $exception) {
            // Couldn't load a class
            return;
        }

        if (is_array($impl) && in_array(ActionInterface::class, $impl, true)) {
            $methodsDefined = false;
            foreach ($impl as $i) {
                if (preg_match('/\\\Http[a-z]+ActionInterface$/i', $i)) {
                    $methodsDefined = true;
                    break;
                }
            }
            if (!$methodsDefined) {
                $this->addViolation($node, [$node->getFullQualifiedName()]);
            }
        }
    }
}
