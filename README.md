# magento2-fix-dynamic-properties
### "PHP Deprecated: Creation of dynamic property is deprecated" problem
Magento 2.4.6 switched to php 8.2, where respect to php 8.1 the dynamic properties have been  deprecated. What does it means?

A class like this:
```
<?php
namespace Vendor\Module\Example;

class Test {
    /**
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface
     */
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface
    ) {
        $this->orderRepositoryInterface = $orderRepositoryInterface;
    }

    // ... logics
}
```

During compilation (if is a cron or a command) or during run-time execution in frontend will give an error like:
```
PHP Deprecated: Creation of dynamic property is deprecated
```
because you need to explicitally declare ion the class the variable:
```
/** @var \Magento\Sales\Api\OrderRepositoryInterface */
protected $orderRepositoryInterface
```

Maybe in your project there are some classes with this problem and if you need to upgrade from a Magento version requiring php<=8.1 to a Magento version requiring php>=8.2 you'll have to deal with it.

More information here [https://www.zagonico.com/magento-2-4-6-dynamic-properties-are-deprecated/](https://www.zagonico.com/magento-2-4-6-dynamic-properties-are-deprecated/).

### Usage
**Syntax:**
`fix_dynamic_properties.php <verbose> <solve> <directory> <only_this>`
- verbose: 0 only show the modifications done, 1 show more info on the source analyzed
- solve: 0 does not solve the errors found, 1 solve the errors found
- directory: directory that will be recursively solved
- only_this: if specified only analyze this file

**Notes**
- put fix_dynamic_properties.php in the root of Magento
- launch `php fix_dynamic_properties.php 0 0 app/code/ModuleToFix/` to emulate the correction
- launch `php fix_dynamic_properties.php 0 1 app/code/ModuleToFix/` to modify the files
- it also can run for a single file `php fix_dynamic_properties.php 0 0 app/code/ModuleToFix/ app/code/ModuleToFix/Model/MyModelToFix.php`
- the first zero in parameters is for verbose mode, you shouldn't need it
