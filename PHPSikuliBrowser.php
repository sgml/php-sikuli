<?php
/**
 * PHPSikuliBrowser is an extension to PHPSikuli.
 *
 * @category  PHP
 * @package   php-sikuli
 * @copyright 2013 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/php-sikuli/blob/master/licence.txt BSD Licence
 * @link      https://github.com/squizlabs/php-sikuli
 */

require_once 'PHPSikuli.php';

class PHPSikuliBrowser extends PHPSikuli
{

    /**
     * Browser window range object.
     *
     * @var string
     */
    private $_window = NULL;

    /**
     * Size of the browser window.
     *
     * @var array
     */
    private $_windowSize = NULL;

    /**
     * The browserid.
     *
     * @var string
     */
    private $_browserid = NULL;

    /**
     * The location of the top left corner of the page on the screen.
     *
     * @var array
     */
    private $_pageTopLeft = NULL;

    /**
     * Temporary directory used during script execution.
     *
     * @var string
     */
    private $_tmpDir = NULL;

    /**
     * List of supported browsers.
     *
     * @var array
     */
    private $_supportedBrowsers = array(
                                   'firefox' => 'Firefox',
                                   'chrome'  => 'Google Chrome',
                                   'safari'  => 'Safari',
                                   'ie8'     => 'Internet Explorer 8',
                                   'ie9'     => 'Internet Explorer 9',
                                   'ie10'    => 'Internet Explorer 10',
                                  );

    /**
     * Default size of the browser window.
     *
     * @var array
     */
    private $_defaultWindowSize = array(
                                   'w' => 1270,
                                   'h' => 900,
                                  );


    /**
     * Constructor.
     *
     * @param string $browser The browser to use.
     */
    public function __construct($browser)
    {
        $this->_tmpDir = dirname(__FILE__).'/tmp';
        if (file_exists($this->_tmpDir) === FALSE) {
            mkdir($this->_tmpDir, 0777, TRUE);
        } else {
            $cmd = 'rm -rf '.$this->_tmpDir.'/*';
            exec($cmd);
        }

        chmod($this->_tmpDir, 0777);

        parent::__construct();
        $this->_setBrowser($browser);

    }//end __construct()


    /**
     * Resets the Sikuli connection.
     *
     * @return void
     */
    public function resetConnection()
    {
        $this->stopJSPolling();

        parent::resetConnection();
        $this->_windowSize = NULL;
        $this->_setBrowser($this->_browserid);

    }//end resetConnection()


    /**
     * Reloads the page.
     *
     * @return void
     */
    public function reloadPage()
    {
        $this->stopJSPolling();
        $this->keyDown('Key.CMD + r');

    }//end reloadPage()


    /**
     * Sets the browser URL to the specified URL.
     *
     * @param string $url The new URL.
     *
     * @return void
     */
    public function goToURL($url)
    {
        $this->keyDown('Key.CMD+l');
        $this->type($url);
        $this->keyDown('Key.ENTER');
        sleep(1);

    }//end goToURL()


    /**
     * Type the text at the current focused input field or at a click point specified by PSMRL.
     *
     * @param string $text      The text to type.
     * @param string $modifiers Key modifiers.
     * @param string $psmrl     PSMRL variable.
     *
     * @return integer
     */
    public function type($text, $modifiers=NULL, $psmrl=NULL)
    {
        // We may need to increase the timeout when typing. It takes about 5s to
        // type 100 characters.
        $length  = strlen($text);
        $timeout = (int) (($length * 5) / 100);

        $currentTimeout = NULL;
        if ($timeout > 10) {
            $timeout       += 5;
            $currentTimeout = $this->sikuli->setSikuliCMDTimeout($timeout);
        }

        $result = parent::type($text, $modifiers, $psmrl);

        if ($timeout > 10) {
            $this->sikuli->setSikuliCMDTimeout($currentTimeout);
        }

        return $result;

    }//end type()


    /**
     * Returns the rectangle for a DOM element found using the specified selector.
     *
     * @param string  $selector The jQuery selector to use for finding the element.
     * @param integer $index    The element index of the resulting array.
     *
     * @return array
     */
    public function getBoundingRectangle($selector, $index=0)
    {
        $rect = $this->execJS('PHPSikuliBrowser.getBoundingRectangle("'.$selector.'", '.$index.')');
        return $rect;

    }//end getBoundingRectangle()


    /**
     * Clicks an element in the content.
     *
     * @param string  $selector   The jQuery selector to use for finding the element.
     * @param integer $index      The element index of the resulting array.
     * @param boolean $rightClick If TRUE then right mouse button is used.
     *
     * @return void
     */
    public function clickElement($selector, $index=0, $rightClick=FALSE)
    {
        $elemRect = $this->getBoundingRectangle($selector, $index);
        $region   = $this->getRegionOnPage($elemRect);

        // Click the element.
        if ($rightClick !== TRUE) {
            $this->click($region);
        } else {
            $this->rightClick($region);
        }

    }//end clickElement()


    /**
     * Executes the specified JavaScript and returns its result.
     *
     * @param string  $js            The JavaScript to execute.
     * @param boolean $noReturnValue If TRUE then JS has no return value and NULL
     *                               will be returned to speed up execution.
     * @param boolean $asynchronous  If TURE then the JS that is being executed is
     *                               asynchronous.
     *
     * @return string
     * @throws Exception If there is a Selenium error.
     */
    public function execJS($js, $noReturnValue=FALSE, $asynchronous=FALSE)
    {
        if ($asynchronous === TRUE) {
            $js = '__asynchronous__'.$js;
        }

        $this->debug('ExecJS: '.$js);

        clearstatcache();
        if (file_exists($this->_tmpDir.'/jsres.tmp') === TRUE) {
            unlink($this->_tmpDir.'/jsres.tmp');
        }

        file_put_contents($this->_tmpDir.'/jsexec.tmp', $js);
        chmod($this->_tmpDir.'/jsexec.tmp', 0777);

        $startTime = microtime(TRUE);
        $timeout   = 3;
        while (file_exists($this->_tmpDir.'/jsres.tmp') === FALSE) {
            if ((microtime(TRUE) - $startTime) > $timeout) {
                break;
            }

            usleep(50000);
        }

        $result = NULL;
        if (file_exists($this->_tmpDir.'/jsres.tmp') === TRUE) {
            $result = file_get_contents($this->_tmpDir.'/jsres.tmp');
            unlink($this->_tmpDir.'/jsres.tmp');

            if ($result === 'undefined' || trim($result) === '') {
                return NULL;
            }

            $result = json_decode($result, TRUE);

            if (is_string($result) === TRUE) {
                $result = str_replace("\r\n", '\n', $result);
                $result = str_replace("\n", '\n', $result);
            }
        }

        return $result;

    }//end execJS()


    /**
     * Stops the JS polling for commands.
     *
     * @return void
     */
    public function stopJSPolling()
    {
        $this->execJS('PHPSikuliBrowser.stopPolling()');

        if (file_exists($this->_tmpDir.'/jsres.tmp') === TRUE) {
            unlink($this->_tmpDir.'/jsres.tmp');
        }

        if (file_exists($this->_tmpDir.'/jsexec.tmp') === TRUE) {
            unlink($this->_tmpDir.'/jsexec.tmp');
        }

    }//end stopJSPolling()


    /**
     * Returns a new Region object relative to the top left of the test page.
     *
     * @param array $rect The rectangle (x1, y1, x2, y2).
     *
     * @return string
     */
    public function getRegionOnPage(array $rect)
    {
        $pageLoc = $this->getPageTopLeft();

        $x = (int) ($pageLoc['x'] + $rect['x1']);
        $y = (int) ($pageLoc['y'] + $rect['y1']);
        $w = (int) ($rect['x2'] - $rect['x1']);
        $h = (int) ($rect['y2'] - $rect['y1']);

        $region = $this->createRegion($x, $y, $w, $h);
        return $region;

    }//end getRegionOnPage()


    /**
     * Returns the given page X location relative to the screen.
     *
     * @param integer $x The x location relative to the page.
     *
     * @return integer
     */
    public function getPageXRelativeToScreen($x)
    {
        $pageLoc = $this->getPageTopLeft();

        $x = ($pageLoc['x'] + $x);
        return $x;

    }//end getPageXRelativeToScreen()


    /**
     * Returns the given page X location relative to the screen.
     *
     * @param integer $y The x location relative to the page.
     *
     * @return integer
     */
    public function getPageYRelativeToScreen($y)
    {
        $pageLoc = $this->getPageTopLeft();

        $y = ($pageLoc['y'] + $y);
        return $y;

    }//end getPageYRelativeToScreen()


    /**
     * Returns the X position of given location relative to the page.
     *
     * @param string $loc The location variable.
     *
     * @return integer
     */
    public function getPageX($loc)
    {
        $pageLoc = $this->getPageTopLeft();
        $x       = ($this->getX($loc) - $pageLoc['x']);

        return $x;

    }//end getPageX()


    /**
     * Returns the Y position of given location relative to the page.
     *
     * @param string $loc The location variable.
     *
     * @return integer
     */
    public function getPageY($loc)
    {
        $pageLoc = $this->getPageTopLeft();
        $y       = ($this->getY($loc) - $pageLoc['y']);

        return $y;

    }//end getPageY()


    /**
     * Returns the location of the page relative to the screen.
     *
     * @return array
     */
    public function getPageTopLeft()
    {
        if ($this->_pageTopLeft === NULL) {
            // Get the JS to display the window-target icon.
            $this->execJS('PHPSikuliBrowser.showTargetIcon()');

            $targetIcon = $this->find(dirname(__FILE__).'/window-target.png');
            $topLeft    = $this->getTopLeft($targetIcon);
            $loc        = array(
                           'x' => $this->getX($topLeft),
                           'y' => $this->getY($topLeft),
                          );

            $this->_pageTopLeft = $loc;
            $this->execJS('PHPSikuliBrowser.hideTargetIcon()');
        }

        return $this->_pageTopLeft;

    }//end getPageTopLeft()


    /**
     * Sets the browser to be used.
     *
     * @param string $browser A valid browser id (E.g. firefox).
     *
     * @return void
     * @throws Exception If the specified browser is not supported.
     */
    private function _setBrowser($browser)
    {
        if (isset($this->_supportedBrowsers[$browser]) === FALSE) {
            throw new Exception('Browser is not supported');
        }

        $appName = $browser;
        if ($this->getOS() === 'windows') {
            if ($appName === 'Google Chrome') {
                $appName = '- Google Chrome';
            } else if ($appName === 'Firefox') {
                $appName = 'Mozilla Firefox';
            } else if ($appName === 'IE8' || $appName === 'IE9') {
                $appName = 'Windows Internet Explorer';
            }
        } else {
            $appName = $this->getBrowserName($browser);
        }

        $app = $this->switchApp($appName);
        if ($this->getOS() !== 'windows') {
            $windowNum = 0;
            switch ($appName) {
                case 'Google Chrome':
                    $windowNum = 1;
                break;

                default:
                    $windowNum = 0;
                break;
            }

            $this->_window = $this->callFunc(
                'window',
                array($windowNum),
                $app,
                TRUE
            );
        } else {
            $this->_window = $app;
        }//end if

        $this->_browserid = $browser;

        $this->addCacheVar($this->_window);

        // Resize the browser.
        $this->resize();

    }//end _setBrowser()


    /**
     * Returns the id of the browser.
     *
     * @return string
     */
    public function getBrowserid()
    {
        return $this->_browserid;

    }//end getBrowserid()


    /**
     * Returns the name of the current browser.
     *
     * @param string $browserid Id of the browser.
     *
     * @return string
     */
    protected function getBrowserName($browserid=NULL)
    {
        if ($browserid === NULL) {
            $browserid = $this->getBrowserid();
        }

        return $this->_supportedBrowsers[$browserid];

    }//end getBrowserName()


    /**
     * Returns the default browser window size.
     *
     * @return array
     */
    public function getDefaultWindowSize()
    {
        return $this->_defaultWindowSize;

    }//end getDefaultWindowSize()


    /**
     * Set the default browser window size.
     *
     * @param integer $w The width of the window.
     * @param integer $h The height of the window.
     *
     * @return void
     */
    public function setDefaultWindowSize($w, $h)
    {
        $this->_defaultWindowSize = array(
                                     'w' => $w,
                                     'h' => $h,
                                    );

    }//end setDefaultWindowSize()


    /**
     * Resizes the browser window.
     *
     * @param integer $w The width of the window.
     * @param integer $h The height of the window.
     *
     * @return void
     */
    public function resize($w=NULL, $h=NULL)
    {
        if ($w === NULL || $h === NULL) {
            $size = $this->getDefaultWindowSize();

            if ($w === NULL) {
                $w = $size['w'];
            }

            if ($h === NULL) {
                $h = $size['h'];
            }
        }

        if (is_array($this->_windowSize) === TRUE) {
            if ($this->_windowSize['w'] === $w && $this->_windowSize['h'] === $h) {
                return;
            }
        }

        $window = $this->getBrowserWindow();

        $bottomRight = $this->getBottomRight($window);

        if ($this->getOS() === 'windows') {
            $bottomRight = $this->createLocation(
                ($this->getX($bottomRight) - 5),
                ($this->getY($bottomRight) - 5)
            );
        }

        $browserX = $this->getX($window);
        $browserY = $this->getY($window);
        $locX     = ($browserX + $w);
        $locY     = ($browserY + $h);

        $newLocation = $this->createLocation($locX, $locY);

        $this->dragDrop($bottomRight, $newLocation);

        // Update the window object.
        $this->removeCacheVar($this->_window);
        $this->_window = $this->createRegion($browserX, $browserY, $locX, $locY);
        $this->addCacheVar($this->_window);

        $this->_windowSize = array(
                              'w' => $w,
                              'h' => $h,
                             );

        // Set the default region of the find operations.
        $this->setDefaultRegion($this->_window);

    }//end resize()


    /**
     * Returns the region of the browser window.
     *
     * @return string
     */
    public function getBrowserWindow()
    {
        return $this->_window;

    }//end getBrowserWindow()


    /**
     * Returns the size of the browser window.
     *
     * @return array
     */
    public function getWindowSize()
    {
        $w = $this->getW($this->getBrowserWindow());
        $h = $this->getH($this->getBrowserWindow());

        $size = array(
                 'w' => $w,
                 'h' => $h,
                );

        return $size;

    }//end getWindowSize()


}//end class

?>