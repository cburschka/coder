<?php
/**
 * \Drupal\Sniffs\Classes\UseStatementOrderSniff.
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @link     http://pear.php.net/package/PHP_CodeSniffer
 */

namespace Drupal\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Checks for "use" statements that are not in alphabetical order.
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @link     http://pear.php.net/package/PHP_CodeSniffer
 */
class UseStatementOrderSniff implements Sniff
{


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array<int|string>
     */
    public function register()
    {
        return [T_USE];

    }//end register()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in
     *                                               the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Only check use statements in the global scope.
        if (empty($tokens[$stackPtr]['conditions']) === false) {
            return;
        }

        $class = $this->getFQN($phpcsFile, $stackPtr);

        $previousUse   = $phpcsFile->findPrevious(T_USE, ($stackPtr - 1));
        $previousClass = $this->getFQN($phpcsFile, $previousUse);

        if ($previousClass === null || strcmp($previousClass, $class) <= 0) {
            return;
        }

        $warning = 'Use statements are not in alphabetical order';
        $fix     = $phpcsFile->addFixableError($warning, $stackPtr, 'UnorderedUse');

        if ($fix === true) {
            $phpcsFile->fixer->beginChangeset();

            // Read the use statement and then remove it.
            $semiColon = $phpcsFile->findEndOfStatement($stackPtr);
            $useStmt   = $phpcsFile->getTokensAsString($stackPtr, ($semiColon - $stackPtr + 1));
            for ($i = $stackPtr; $i <= $semiColon; $i++) {
                $phpcsFile->fixer->replaceToken($i, '');
            }

            // Also remove whitespace after the semicolon (new lines).
            while (isset($tokens[$i]) === true && $tokens[$i]['code'] === T_WHITESPACE) {
                $phpcsFile->fixer->replaceToken($i, '');
                if (strpos($tokens[$i]['content'], $phpcsFile->eolChar) !== false) {
                    break;
                }

                $i++;
            }

            $insertBefore = $previousUse;

            // Seek backward through use statements for as long as their class names are lexicographically greater.
            while ($previousClass !== null && strcmp($previousClass, $class) > 0) {
                $insertBefore  = $previousUse;
                $previousUse   = $phpcsFile->findPrevious(T_USE, ($previousUse - 1));
                $previousClass = $this->getFQN($phpcsFile, $previousUse);
            }

            // Reinsert the deleted use statement before that use statement.
            // This effectively creates an insertion sort, as the sniff will rerun until it passes.
            $phpcsFile->fixer->addNewlineBefore($insertBefore);
            $phpcsFile->fixer->addContentBefore($insertBefore, $useStmt);

            $phpcsFile->fixer->endChangeset();
        }//end if

    }//end process()


    /**
     * Extract the FQN imported by a use statement.
     *
     * @param File      $phpcsFile The file being scanned.
     * @param int|false $usePtr    The index of the "use" keyword, or false.
     *
     * @return string|null    The value of the imported FQN, or null for invalid statements.
     */
    private function getFQN(File $phpcsFile, $usePtr)
    {
        if ($usePtr === false) {
            return null;
        }

        $tokens = $phpcsFile->getTokens();

        // Seek to the end of the statement.
        $end = $phpcsFile->findEndOfStatement($usePtr);
        if ($tokens[$end]['code'] !== T_SEMICOLON) {
            return null;
        }

        // If the use statement is aliased, only seek to the "as" keyword.
        $as = $phpcsFile->findNext(T_AS, $usePtr, $end);
        if ($as !== false) {
            $end = $as;
        }

        $classPtr = ($usePtr + 1);
        return trim($phpcsFile->getTokensAsString($classPtr, ($end - $classPtr)));

    }//end getClass()


}//end class
