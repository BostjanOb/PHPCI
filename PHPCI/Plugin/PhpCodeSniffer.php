<?php
/**
 * PHPCI - Continuous Integration for PHP
 *
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCI\Plugin;

use PHPCI;
use PHPCI\Builder;
use PHPCI\Model\Build;
use PHPCI\Model\BuildError;

/**
* PHP Code Sniffer Plugin - Allows PHP Code Sniffer testing.
* @author       Dan Cryer <dan@block8.co.uk>
* @package      PHPCI
* @subpackage   Plugins
*/
class PhpCodeSniffer implements PHPCI\Plugin, PHPCI\ZeroConfigPlugin
{
    /**
     * @var \PHPCI\Builder
     */
    protected $phpci;

    /**
     * @var array
     */
    protected $suffixes;

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $standard;

    /**
     * @var string
     */
    protected $tab_width;

    /**
     * @var string
     */
    protected $encoding;

    /**
     * @var int
     */
    protected $allowed_errors;

    /**
     * @var int
     */
    protected $allowed_warnings;

    /**
     * @var string, based on the assumption the root may not hold the code to be
     * tested, exteds the base path
     */
    protected $path;

    /**
     * @var array - paths to ignore
     */
    protected $ignore;

    /**
     * Check if this plugin can be executed.
     * @param $stage
     * @param Builder $builder
     * @param Build $build
     * @return bool
     */
    public static function canExecute($stage, Builder $builder, Build $build)
    {
        if ($stage == 'test') {
            return true;
        }

        return false;
    }

    /**
     * @param \PHPCI\Builder $phpci
     * @param \PHPCI\Model\Build $build
     * @param array $options
     */
    public function __construct(Builder $phpci, Build $build, array $options = array())
    {
        $this->phpci = $phpci;
        $this->build = $build;
        $this->suffixes = array('php');
        $this->directory = $phpci->buildPath;
        $this->standard = 'PSR2';
        $this->tab_width = '';
        $this->encoding = '';
        $this->path = '';
        $this->ignore = $this->phpci->ignore;
        $this->allowed_warnings = 0;
        $this->allowed_errors = 0;

        if (isset($options['zero_config']) && $options['zero_config']) {
            $this->allowed_warnings = -1;
            $this->allowed_errors = -1;
        }

        if (isset($options['suffixes'])) {
            $this->suffixes = (array)$options['suffixes'];
        }

        if (!empty($options['tab_width'])) {
            $this->tab_width = ' --tab-width='.$options['tab_width'];
        }

        if (!empty($options['encoding'])) {
            $this->encoding = ' --encoding=' . $options['encoding'];
        }

        $this->setOptions($options);
    }

    /**
     * Handle this plugin's options.
     * @param $options
     */
    protected function setOptions($options)
    {
        foreach (array('directory', 'standard', 'path', 'ignore', 'allowed_warnings', 'allowed_errors') as $key) {
            if (array_key_exists($key, $options)) {
                $this->{$key} = $options[$key];
            }
        }
    }

    /**
    * Runs PHP Code Sniffer in a specified directory, to a specified standard.
    */
    public function execute()
    {
        list($ignore, $standard, $suffixes) = $this->getFlags();

        $phpcs = $this->phpci->findBinary('phpcs');

        if ( !is_array($this->path) )
            $this->path = [$this->path];

        array_walk($this->path, function(&$item) {
            $item = escapeshellarg($this->phpci->buildPath . $item);
        });

        $this->phpci->executeCommand($phpcs . ' --version');
        $cmd = $phpcs . ' --colors --report-width=500 --report=full %s %s %s %s %s %s';
        $this->phpci->executeCommand(
            $cmd,
            $standard,
            $suffixes,
            $ignore,
            $this->tab_width,
            $this->encoding,
            implode(' ', $this->path)
        );

        $output = $this->phpci->getLastOutput();

        $errors = preg_match_all('/\|.+ERROR.+\|/m', $output);
        $warnings = preg_match_all('/\|.+WARNING.+\|/m', $output);

        if ( $errors || $warnings ) {
            $this->build->storeMeta('php_code_sniffer:message', "Errors: {$errors}; Warnings: {$warnings}");
        }

        if ( ($this->allowed_warnings != -1 && $warnings > $this->allowed_warnings)
            || ($this->allowed_errors != -1 && $errors > $this->allowed_errors)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Process options and produce an arguments string for PHPCS.
     * @return array
     */
    protected function getFlags()
    {
        $ignore = '';
        if (count($this->ignore)) {
            $ignore = ' --ignore=' . implode(',', $this->ignore);
        }

        if (strpos($this->standard, '/') !== false) {
            $standard = ' --standard='.$this->directory.$this->standard;
        } else {
            $standard = ' --standard='.$this->standard;
        }

        $suffixes = '';
        if (count($this->suffixes)) {
            $suffixes = ' --extensions=' . implode(',', $this->suffixes);
        }

        return array($ignore, $standard, $suffixes);
    }
}
