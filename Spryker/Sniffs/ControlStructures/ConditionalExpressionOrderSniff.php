<?php

/**
 * MIT License
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Sniffs\ControlStructures;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use Spryker\Traits\BasicsTrait;

/**
 * Checks that no YODA conditions (reversed order of natural conditions) are being used.
 */
class ConditionalExpressionOrderSniff implements Sniff
{
    use BasicsTrait;

    /**
     * @inheritdoc
     */
    public function register()
    {
        return Tokens::$comparisonTokens;
    }

    /**
     * @inheritdoc
     */
    public function process(File $phpCsFile, $stackPointer)
    {
        $tokens = $phpCsFile->getTokens();

        $prevIndex = $phpCsFile->findPrevious(Tokens::$emptyTokens, ($stackPointer - 1), null, true);
        if (!in_array($tokens[$prevIndex]['code'], [T_TRUE, T_FALSE, T_NULL, T_LNUMBER, T_CONSTANT_ENCAPSED_STRING])) {
            return;
        }

        $prevIndex = $phpCsFile->findPrevious(Tokens::$emptyTokens, ($prevIndex - 1), null, true);
        if (!$prevIndex) {
            return;
        }
        if ($this->isGivenKind(Tokens::$arithmeticTokens, $tokens[$prevIndex])) {
            return;
        }
        if ($this->isGivenKind([T_STRING_CONCAT], $tokens[$prevIndex])) {
            return;
        }

        $error = 'Usage of Yoda conditions is not allowed. Switch the expression order.';
        $prevContent = $tokens[$prevIndex]['content'];

        if (!$this->isGivenKind(Tokens::$assignmentTokens, $tokens[$prevIndex])
            && !$this->isGivenKind(Tokens::$booleanOperators, $tokens[$prevIndex]) && $prevContent !== '('
        ) {
            // Not fixable
            $phpCsFile->addError($error, $stackPointer, 'YodaNotAllowed');
            return;
        }

        //TODO
    }
}
