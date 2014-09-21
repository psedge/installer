<?php
/**
The MIT License (MIT)

Copyright (c) 2014 Peter Sedgewick

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

Peter Sedgewick, TotoMoto
@ 21/09/14 00:29
*/
namespace Installer;

class Installer
{
    public $dir     = 'sql/';
    public $version = '0.1.0';

    protected $pdo     = null;
    protected $connection = array();
    protected $parameters = array();

    /**
     * @param $connection
     * @param null $parameters
     * @throws \Exception
     */
    public function __construct($connection, $parameters = null)
    {
        //Check connection details
        if (!is_array($connection)) {
            throw new \Exception('Installer class must be constructed with an array of connection settings.');
        } else {
            foreach (array('host', 'db', 'user', 'pwd') as $key) {
                if (!isset($connection[$key])) {
                    throw new \Exception('Please provide a valid ' . $key);
                } else {
                    $this->connection[$key] = $connection[$key];
                }
            }
        }

        //Set options and initialise PDO
        if ($parameters) {
            if (isset($parameters['debug']) && $parameters['debug']) {
                $this->parameters['debug'] = true;
            }
        }

        /** @var PDO pdo */
        $host = $this->connection['host'];
        $dbname = $this->connection['db'];
        $this->pdo = new \PDO(
            "mysql:host=$host;dbname=$dbname",
            $this->connection['user'],
            $this->connection['pwd']
        );
    }

    public function getSqlDirectory()
    {
        return $this->dir;
    }

    /**
     * Returns the version that the database is currently at.
     *
     * @return string
     */
    public function getCurrentVersion()
    {
        if (file_exists($this->getSqlDirectory() . 'version.txt')) {
            $txtContents = preg_split('/\n|\r/', file_get_contents($this->getSqlDirectory() . 'version.txt'), -1, PREG_SPLIT_NO_EMPTY);
            if (isset($txtContents[0])) {
                $this->version = $txtContents[0];
            }
        }

        return $this->version;
    }

    protected function getAllUpdates()
    {
        $fileList = scandir($this->getSqlDirectory());
        unset($fileList[0], $fileList[1]);

        $sql = array();
        foreach ($fileList as $fileName) {
            $sql[str_replace('.sql', '', $fileName)] = file_get_contents($this->getSqlDirectory() . $fileName);
        }

        return $sql;
    }

    /**
     * @param $toVersion string
     * @return bool
     */
    public function init($toVersion)
    {
        //No updates
        if ($toVersion == $this->getCurrentVersion()) {
            return true;
        }

        $updates = $this->getAllUpdates();
        foreach ($updates as $version => $statements) {
            if ($version > $this->getCurrentVersion()  && $version <= $toVersion) {
                foreach (explode(';', $statements) as $statement) {
                    try {
                        $this->pdo->exec($statement);
                    } catch (\Exception $e) {
                        $this->log($e);
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param $toVersion string
     * @returns bool
     */
    protected function updateVersion($toVersion)
    {
        file_put_contents($this->getSqlDirectory() . 'version.txt', $toVersion);
        return true;
    }

    /**
     * @param $message
     * @return bool
     */
    protected function log($message)
    {
        file_put_contents($this->getSqlDirectory() . $this->getLogFilename(), $message, FILE_APPEND);
        return true;
    }

    protected function getLogFilename()
    {
        return 'installer.log';
    }
}