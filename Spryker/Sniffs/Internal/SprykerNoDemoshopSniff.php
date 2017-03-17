<?php
/**
 * (c) Spryker Systems GmbH copyright protected.
 */

namespace Spryker\Sniffs\Internal;

use PHP_CodeSniffer_File;
use Spryker\Sniffs\AbstractSniffs\AbstractSprykerSniff;

/**
 * Makes sure the project specific non-split code does not leak into the demoshop repository.
 */
class SprykerNoDemoshopSniff extends AbstractSprykerSniff
{

    /**
     * @var bool|null
     */
    protected static $isDemoshop = null;

    /**
     * @inheritdoc
     */
    public function register()
    {
        return [T_NAMESPACE];
    }

    /**
     * @inheritdoc
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        if (!$this->isDemoshopCode($phpcsFile)) {
            return;
        }

        $file = $phpcsFile->getFilename();
        $content = file_get_contents($file);

        if (!preg_match('/\* @project\b/', $content)) {
            return;
        }

        $phpcsFile->addError('No project only code should be merged into Spryker demoshop.', $stackPtr, 'InvalidContent');
    }

    /**
     * @param \PHP_CodeSniffer_File $phpCsFile
     *
     * @return bool
     */
    protected function isDemoshopCode(PHP_CodeSniffer_File $phpCsFile)
    {
        if (static::$isDemoshop !== null) {
            return static::$isDemoshop;
        }

        $positionSprykerCore = strpos($phpCsFile->getFilename(), '/src/');
        if (!$positionSprykerCore) {
            return false;
        }

        $file = substr($phpCsFile->getFilename(), 0, $positionSprykerCore) . '/composer.json';
        if (!is_file($file)) {
            static::$isDemoshop = false;
            return static::$isDemoshop;
        }

        $content = file_get_contents($file);
        static::$isDemoshop = (bool)preg_match('#"name":\s*"spryker/demoshop"#', $content, $matches);

        return static::$isDemoshop;
    }

}
