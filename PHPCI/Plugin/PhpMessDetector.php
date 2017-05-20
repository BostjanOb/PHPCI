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

/**
* PHP Mess Detector Plugin - Allows PHP Mess Detector testing.
* @author       Dan Cryer <dan@block8.co.uk>
* @package      PHPCI
* @subpackage   Plugins
*/
class PhpMessDetector implements PHPCI\Plugin, PHPCI\ZeroConfigPlugin
{
    /**
     * @var \PHPCI\Builder
     */
    protected $phpci;

    /**
     * @var \PHPCI\Model\Build
     */
    protected $build;

    /**
     * @var array
     */
    protected $suffixes;

    /**
     * @var string, based on the assumption the root may not hold the code to be
     * tested, extends the base path only if the provided path is relative. Absolute
     * paths are used verbatim
     */
    protected $path;

    /**
     * @var array - paths to ignore
     */
    protected $ignore;

    /**
     * Array of PHPMD rules. Can be one of the builtins (codesize, unusedcode, naming, design, controversial)
     * or a filename (detected by checking for a / in it), either absolute or relative to the project root.
     * @var array
     */
    protected $rules;

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
        $this->phpci = $phpci;
        $this->build = $build;
        $this->suffixes = array('php');
        $this->ignore = $phpci->ignore;
        $this->path = '';
        $this->rules = array('codesize', 'unusedcode', 'naming');
        $this->allowed_warnings = 0;

        if (isset($options['zero_config']) && $options['zero_config']) {
            $this->allowed_warnings = -1;
        }

        if (!empty($options['path'])) {
            $this->path = $options['path'];
        }

        if (array_key_exists('allowed_warnings', $options)) {
            $this->allowed_warnings = (int)$options['allowed_warnings'];
        }

        foreach (array('rules', 'ignore', 'suffixes') as $key) {
            $this->overrideSetting($options, $key);
        }
    }

    /**
     * Runs PHP Mess Detector in a specified directory.
     */
    public function execute()
    {
        if (!$this->tryAndProcessRules()) {
            return false;
        }

        $phpmd = $this->phpci->findBinary('phpmd');

        $this->phpci->executeCommand($phpmd . ' --version');
        $this->executePhpMd($phpmd);

        $errorCount = substr_count(trim($this->phpci->getLastOutput()), "\n");
        $this->build->storeMeta('phpmd-warnings', $errorCount);

        if ($this->allowed_warnings != -1 && $errorCount > $this->allowed_warnings) {
            return false;
        }

        return true;
    }

    /**
     * Override a default setting.
     * @param $options
     * @param $key
     */
    protected function overrideSetting($options, $key)
    {
        if (isset($options[$key]) && is_array($options[$key])) {
            $this->{$key} = $options[$key];
        }
    }

    /**
     * Try and process the rules parameter from phpci.yml.
     * @return bool
     */
    protected function tryAndProcessRules()
    {
        if (!empty($this->rules) && !is_array($this->rules)) {
            $this->phpci->logFailure('The "rules" option must be an array.');
            return false;
        }

        foreach ($this->rules as &$rule) {
            if (strpos($rule, '/') !== false) {
                $rule = $this->phpci->buildPath . $rule;
            }
        }

        return true;
    }

    /**
     * Execute PHP Mess Detector.
     * @param $binaryPath
     */
    protected function executePhpMd($binaryPath)
    {
        $cmd = $binaryPath . ' "%s" text %s %s %s';

        $path = $this->getTargetPath();

        $ignore = '';
        if (count($this->ignore)) {
            $ignore = ' --exclude ' . implode(',', $this->ignore);
        }

        $suffixes = '';
        if (count($this->suffixes)) {
            $suffixes = ' --suffixes ' . implode(',', $this->suffixes);
        }

        // Disable exec output logging, as we don't want the XML report in the log:
//        $this->phpci->logExecOutput(false);

        // Run PHPMD:
        $this->phpci->executeCommand(
            $cmd,
            $path,
            implode(',', $this->rules),
            $ignore,
            $suffixes
        );

        // Re-enable exec output logging:
//        $this->phpci->logExecOutput(true);
    }

    /**
     * Get the path PHPMD should be run against.
     * @return string
     */
    protected function getTargetPath()
    {
        $path = $this->phpci->buildPath . $this->path;
        if (!empty($this->path) && $this->path{0} == '/') {
            $path = $this->path;
            return $path;
        }
        return $path;
    }
}
