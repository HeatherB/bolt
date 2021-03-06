<?php

namespace Bolt\Composer\Action;

use Bolt\Translation\Translator as Trans;
use Composer\Json\JsonFile;
use Silex\Application;

/**
 * Initialise Composer JSON file class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class BoltExtendJson extends BaseAction
{
    /**
     * Convenience function to generalise the library.
     *
     * @param string $file
     * @param array  $data
     */
    public function execute($file, array $data = [])
    {
        $this->initJson($file, $data);
    }

    /**
     * Initialise a JSON file at given location with optional data input.
     *
     * @param string $file
     * @param array  $data
     */
    public function initJson($file, array $data = [])
    {
        $jsonFile = new JsonFile($file);
        $jsonFile->write($data);
    }

    /**
     * Set up Composer JSON file.
     *
     * @return array|null
     */
    public function updateJson()
    {
        if (!is_file($this->getOption('composerjson'))) {
            $this->initJson($this->getOption('composerjson'));
        }

        $jsonFile = new JsonFile($this->getOption('composerjson'));
        if ($jsonFile->exists()) {
            $json = $jsonorig = $jsonFile->read();

            // Workaround Bolt 2.0 installs with "require": []
            if (isset($json['require']) && empty($json['require'])) {
                unset($json['require']);
            }
        } else {
            // Error
            $this->messages[] = Trans::__(
                "The Bolt extensions file '%composerjson%' isn't readable.",
                ['%composerjson%' => $this->getOption('composerjson')]
            );

            $this->app['extend.writeable'] = false;
            $this->app['extend.online'] = false;

            return null;
        }

        $pathToWeb = $this->app['resources']->findRelativePath($this->app['resources']->getPath('extensions'), $this->app['resources']->getPath('web'));

        // Enforce standard settings
        $json['repositories']['packagist'] = false;
        $json['repositories']['bolt'] = [
            'type' => 'composer',
            'url'  => $this->app['extend.site'] . 'satis/'
        ];
        $json['minimum-stability'] = $this->app['config']->get('general/extensions/stability', 'stable');
        $json['prefer-stable'] = true;
        $json['config'] = [
            'discard-changes'   => true,
            'preferred-install' => 'dist'
        ];
        $json['provide']['bolt/bolt'] = $this->app['bolt_version'];
        $json['extra']['bolt-web-path'] = $pathToWeb;
        $json['autoload']['psr-4']['Bolt\\Composer\\'] = '';
        $json['scripts'] = [
            'post-package-install' => 'Bolt\\Composer\\ExtensionInstaller::handle',
            'post-package-update'  => 'Bolt\\Composer\\ExtensionInstaller::handle'
        ];

        // Write out the file, but only if it's actually changed, and if it's writable.
        if ($json != $jsonorig) {
            try {
                umask(0000);
                $jsonFile->write($json);
            } catch (\Exception $e) {
                $this->messages[] = Trans::__(
                    'The Bolt extensions Repo at %repository% is currently unavailable. Check your connection and try again shortly.',
                    ['%repository%' => $this->app['extend.site']]
                );
            }
        }

        return $json;
    }
}
