<?php

namespace Baobab\Configuration;

use Baobab\Configuration\Exception\ConfigurationNotFoundException;
use Baobab\Configuration\Exception\UnknownSectionException;
use Baobab\Configuration\Initializer\Initializer;
use Baobab\Configuration\Parser\PhpParser;
use Baobab\Facade\Baobab;
use Baobab\Helper\Hooks;
use Baobab\Helper\Paths;
use Baobab\Theme\Exception\ThemeDeclarationException;

/**
 * Class Configuration
 * @package Baobab\Configuration
 *
 *          Parses and applies the theme configuration files
 */
class Configuration
{
    /** The namespace where we find our core initializers */
    const DEFAULT_INITIALIZER_NS = '\Baobab\Configuration\Initializer';

    //------------------------------------------------------------------------------------------------------------------
    // Configuration factory

    /**
     * Create the theme configuration. The object will be created and configuration files will be parsed.
     *
     * @param array $mapping   The mapping between configuration files and classes. Some default values are provided and
     *                         the parameter will be merged with the default mappings.
     *
     * @return Configuration The configuration object
     */
    public static function create($mapping = array())
    {
        $defaultMapping = array(
            'autoload'         => self::DEFAULT_INITIALIZER_NS . '\Autoload',
            'dependencies'     => self::DEFAULT_INITIALIZER_NS . '\Dependencies',
            'general-settings' => self::DEFAULT_INITIALIZER_NS . '\ThemeSettings',
            'customizer'       => self::DEFAULT_INITIALIZER_NS . '\Customizer',
            'image-sizes'      => self::DEFAULT_INITIALIZER_NS . '\ImageSizes',
            'widget-areas'     => self::DEFAULT_INITIALIZER_NS . '\WidgetAreas',
            'menu-locations'   => self::DEFAULT_INITIALIZER_NS . '\MenuLocations',
            'theme-supports'   => self::DEFAULT_INITIALIZER_NS . '\ThemeSupports',
            'assets'           => self::DEFAULT_INITIALIZER_NS . '\Assets',
            'templates'        => self::DEFAULT_INITIALIZER_NS . '\Templates'
        );

        $finalMapping = array_merge($defaultMapping, $mapping);

        return new Configuration($finalMapping);
    }

    /** @var Initializer[] Array of Initializer objects */
    protected $initializers = array();

    /**
     * Hidden constructor. Use Configuration::setup
     *
     * @param array $mapping The mapping between configuration files and classes.
     *
     * @throws ConfigurationNotFoundException
     */
    protected function __construct($mapping)
    {
        // Supported parsers for configuration files
        $parsers = array(
            'config.php' => new PhpParser()
        );

        // Parse each file
        $configRoot = Paths::configuration();
        $env = Baobab::environment();
        foreach ($mapping as $file => $className)
        {
            $fileLoaded = false;
            $data = array();

            $pathStack = array(
                $configRoot . '/' . $env . '/' . $file,
                $configRoot . '/' . $file
            );

            foreach ($pathStack as $fullPath)
            {
                $tempData = null;

                /** @var \Baobab\Configuration\Parser\Parser $parser */
                foreach ($parsers as $ext => $parser)
                {
                    if (file_exists($fullPath . '.' . $ext))
                    {
                        $fileLoaded = true;
                        $tempData = $parser->parse($fullPath);
                        break;
                    }
                }

                if ($tempData != null)
                {
                    $data = array_merge($tempData, $data);
                }
            }

            if ($fileLoaded)
            {
                $this->initializers[$file] = new $className($file, $data);

                // Provide some hooks
                do_action('baobab/configuration/file-loaded?file=' . $file);
            }
        }
    }

    /**
     * Apply the configuration
     */
    public function apply()
    {
        foreach ($this->initializers as $id => $initializer)
        {
            do_action('baobab/configuration/before-initializer?id=' . $id);
            $initializer->run();
            do_action('baobab/configuration/after-initializer?id=' . $id);
        }
    }

    /**
     * Get the value of a setting in the configuration. If that setting is not found, an exception will be thrown.
     *
     * @param string $section The configuration section where to find the setting
     * @param string $key     The key of the setting we are interested about
     *
     * @return mixed The setting value
     */
    public function getOrThrow($section, $key)
    {
        if ( !isset($this->initializers[$section]))
        {
            throw new UnknownSectionException($section);
        }

        return $this->initializers[$section]->getSettingOrThrow($key);
    }

    /**
     * Get the value of a setting in the configuration. If that setting is not found, return the provided
     * default value.
     *
     * @param string $section      The configuration section where to find the setting
     * @param string $key          The key of the setting we are interested about
     * @param mixed  $defaultValue The default value to return if not found
     *
     * @return mixed The setting value or the default value
     */
    public function get($section, $key, $defaultValue = null)
    {
        if ( !isset($this->initializers[$section]))
        {
            return $defaultValue;
        }

        return $this->initializers[$section]->getSetting($key, $defaultValue);
    }
}