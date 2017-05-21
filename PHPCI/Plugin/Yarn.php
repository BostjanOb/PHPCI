<?php
/**
 * PHPCI - Continuous Integration for PHP
 *
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCI\Plugin;

use PHPCI\Builder;
use PHPCI\Model\Build;

/**
 * Grunt Plugin - Provides access to grunt functionality.
 * @author       Tobias Tom <t.tom@succont.de>
 * @package      PHPCI
 * @subpackage   Plugins
 */
class Yarn implements \PHPCI\Plugin
{
    protected $directory;
    protected $task;

    protected $phpci;
    protected $build;

    /**
     * Standard Constructor
     *
     * $options['directory'] Output Directory. Default: %BUILDPATH%
     * $options['filename']  Phar Filename. Default: build.phar
     * $options['regexp']    Regular Expression Filename Capture. Default: /\.php$/
     * $options['stub']      Stub Content. No Default Value
     *
     * @param Builder $phpci
     * @param Build   $build
     * @param array   $options
     */
    public function __construct(Builder $phpci, Build $build, array $options = array())
    {
        $path = $phpci->buildPath;
        $this->build = $build;
        $this->phpci = $phpci;
        $this->directory = $path;
        $this->task = null;

        // Handle options:
        if (isset($options['directory'])) {
            $this->directory = $path . DIRECTORY_SEPARATOR . $options['directory'];
        }

        if (isset($options['task'])) {
            $this->task = $options['task'];
        }
    }

    /**
     * Executes grunt and runs a specified command (e.g. install / update)
     */
    public function execute()
    {
        // if npm does not work, we cannot use grunt, so we return false
        $cmd = 'cd %s && yarn';
        if (!$this->phpci->executeCommand($cmd, $this->directory)) {
            return false;
        }

        // build the grunt command
        $cmd = 'cd %s && yarn run %s --no-emoji --non-interactive --no-progress';

        // and execute it
        return $this->phpci->executeCommand($cmd, $this->directory, $this->task);
    }
}
