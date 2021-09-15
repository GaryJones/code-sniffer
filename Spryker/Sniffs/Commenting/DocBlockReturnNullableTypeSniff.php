<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spryker\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use RuntimeException;
use SlevomatCodingStandard\Helpers\DocCommentHelper;
use SlevomatCodingStandard\Helpers\FunctionHelper;
use Spryker\Sniffs\AbstractSniffs\AbstractSprykerSniff;

/**
 * Checks for missing/superfluous `|null` in docblock return annotations.
 */
class DocBlockReturnNullableTypeSniff extends AbstractSprykerSniff
{
    /**
     * @inheritDoc
     */
    public function register(): array
    {
        return [
            T_FUNCTION,
        ];
    }

    /**
     * @inheritDoc
     */
    public function process(File $phpCsFile, $stackPointer)
    {
        $returnType = FunctionHelper::findReturnTypeHint($phpCsFile, $stackPointer);

        if ($returnType === null) {
            return;
        }

        $docBlockEndIndex = $this->findRelatedDocBlock($phpCsFile, $stackPointer);
        if (!$docBlockEndIndex) {
            return;
        }

        $tokens = $phpCsFile->getTokens();
        $docBlockStartIndex = $tokens[$docBlockEndIndex]['comment_opener'];

        $docBlockReturnIndex = $this->findDocBlockReturn($phpCsFile, $docBlockStartIndex, $docBlockEndIndex);
        if (!$docBlockReturnIndex) {
            return;
        }

        $nextIndex = $phpCsFile->findNext(T_DOC_COMMENT_STRING, $docBlockReturnIndex + 1, $docBlockEndIndex);
        if (!$nextIndex) {
            return;
        }

        $docBlockReturnTypes = $this->parseDocBlockReturnTypes($phpCsFile, $nextIndex);
        if ($docBlockReturnTypes === null) {
            return;
        }

        if (!$returnType->isNullable()) {
            $this->assertNotNullableReturnType($phpCsFile, $stackPointer, $docBlockReturnTypes);

            return;
        }

        $this->assertRequiredNullableReturnType($phpCsFile, $stackPointer, $docBlockReturnTypes);
    }

    /**
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     * @param int $stackPointer
     *
     * @return string[]|null
     */
    protected function parseDocBlockReturnTypes(File $phpCsFile, int $stackPointer): ?array
    {
        $tokens = $phpCsFile->getTokens();

        $content = $tokens[$stackPointer]['content'];

        // Skip for complex case right now
        if (strpos($content, '<') !== false) {
            return null;
        }

        preg_match('/(@return\s+)(\S+)/', $content, $matches);

        if (!$matches) {
            return [];
        }

        $returnTypes = array_pop($matches);
        $returnTypes = trim($returnTypes);
        $returnTypes = explode('|', $returnTypes);

        return $returnTypes;
    }

    /**
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int $docBlockStartIndex
     * @param int $docBlockEndIndex
     *
     * @return int|null
     */
    protected function findDocBlockReturn(File $phpcsFile, int $docBlockStartIndex, int $docBlockEndIndex): ?int
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $docBlockStartIndex + 1; $i < $docBlockEndIndex; $i++) {
            if (!$this->isGivenKind(T_DOC_COMMENT_TAG, $tokens[$i])) {
                continue;
            }
            if ($tokens[$i]['content'] !== '@return') {
                continue;
            }

            return $i;
        }

        return null;
    }

    /**
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     * @param int $stackPointer
     * @param string[] $docBlockReturnTypes
     *
     * @return void
     */
    public function assertNotNullableReturnType(File $phpCsFile, int $stackPointer, array $docBlockReturnTypes): void
    {
        if (!$docBlockReturnTypes) {
            return;
        }
        if (!in_array('null', $docBlockReturnTypes, true)) {
            return;
        }

        $errorMessage = 'Method should not have `null` in return type in doc block.';
        $fix = $phpCsFile->addFixableError($errorMessage, $stackPointer, 'ReturnNullableInvalid');

        if (!$fix) {
            return;
        }

        $this->removeNullFromDocBlockReturnType($phpCsFile, $stackPointer);
    }

    /**
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     * @param int $stackPointer
     * @param string[] $docBlockReturnTypes
     *
     * @return void
     */
    public function assertRequiredNullableReturnType(
        File $phpCsFile,
        int $stackPointer,
        array $docBlockReturnTypes
    ): void {
        if (!$docBlockReturnTypes) {
            return;
        }
        if (in_array('null', $docBlockReturnTypes, true)) {
            return;
        }

        $errorMessage = 'Method does not have `null` in return type in doc block.';
        $fix = $phpCsFile->addFixableError($errorMessage, $stackPointer, 'ReturnNullableMissing');

        if (!$fix) {
            return;
        }

        $this->addNullToDocBlockReturnType($phpCsFile, $stackPointer);
    }

    /**
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     * @param int $stackPointer
     *
     * @return void
     */
    protected function addNullToDocBlockReturnType(File $phpCsFile, int $stackPointer): void
    {
        $returnTypesToken = $this->getDocBlockReturnTypesToken($phpCsFile, $stackPointer);

        $tokenIndex = $returnTypesToken['index'];
        $returnTypes = $returnTypesToken['token']['content'];
        $returnTypes = trim($returnTypes, '|') . '|null';

        $phpCsFile->fixer->beginChangeset();
        $phpCsFile->fixer->replaceToken($tokenIndex, $returnTypes);
        $phpCsFile->fixer->endChangeset();
    }

    /**
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     * @param int $stackPointer
     *
     * @return void
     */
    protected function removeNullFromDocBlockReturnType(File $phpCsFile, int $stackPointer): void
    {
        $returnTypesToken = $this->getDocBlockReturnTypesToken($phpCsFile, $stackPointer);

        $tokenIndex = $returnTypesToken['index'];
        $returnTypes = explode('|', $returnTypesToken['token']['content']);
        foreach ($returnTypes as $key => $returnType) {
            if ($returnType === 'null') {
                unset($returnTypes[$key]);
            }
        }

        $phpCsFile->fixer->beginChangeset();
        $phpCsFile->fixer->replaceToken($tokenIndex, implode('|', $returnTypes));
        $phpCsFile->fixer->endChangeset();
    }

    /**
     * @param \PHP_CodeSniffer\Files\File $phpCsFile
     * @param int $stackPointer
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    protected function getDocBlockReturnTypesToken(File $phpCsFile, int $stackPointer): array
    {
        $tokens = $phpCsFile->getTokens();

        $docBlockStartIndex = DocCommentHelper::findDocCommentOpenPointer($phpCsFile, $stackPointer);
        $docBlockEndIndex = $this->findRelatedDocBlock($phpCsFile, $stackPointer);

        for ($i = $docBlockEndIndex; $i >= $docBlockStartIndex; $i--) {
            if ($tokens[$i]['content'] !== '@return') {
                continue;
            }

            $returnTypesTokenIndex = $phpCsFile->findNext(
                [T_DOC_COMMENT_WHITESPACE],
                $i + 1,
                null,
                true
            );

            return [
                'index' => $returnTypesTokenIndex,
                'token' => $tokens[$returnTypesTokenIndex],
            ];
        }

        throw new RuntimeException('No token found.');
    }
}
