<?php
//Example :
//$loopManager = new FN_LoopManager("PrintTime", "*-*-* *:*/5:*",true); // every 5 minutes
//$loopManager->run();    
//$loopManager2 = new FN_LoopManager("PrintTime2", "*-*-* *:*/2:*",true); // Execute 2 minutes 
//$loopManager2->run();    
//$loopManager3 = new FN_LoopManager("PrintTime3", "*-*-* *:*/30:05",true); // every 30 seconds at second 05
//$loopManager3->run();    
class FN_LoopManager
{

    private $fileId = 0;
    private $startTime = 0;
    private $lockFile = '';
    private $istanceId = '';
    private $maxExecutionTime = 120;
    private $restartThreshold = 10;
    private $lockTimeout = 10;
    private $callback = "";
    private $timerString = "";
    private $time_performed = false;
    private $debug = false;
    private $stateFile = ''; // File to store timer state

    public function __construct($callback = "", $timerString = "*-*-* *:*:00", $debug = false) //Y-m-d H:i:s
    {
        $this->callback = $callback;
        if ($this->validateTimerString($timerString))
        {
            $this->timerString = $timerString;
        }

        // Generate lock file name based on the current script
        $scriptName = basename($_SERVER['SCRIPT_FILENAME'], '.php');
        $this->istanceId = md5($scriptName) . md5($callback);

        $this->lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->istanceId . '.lock';
        $this->stateFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->istanceId . '.state'; // State file
        $this->debug = $debug;
        $this->setMaxExecutionTime();
        // Load the previous state if it exists
        $this->loadState();
    }
    private function setMaxExecutionTime()
    {
        // Get the current max execution time
        $currentMaxExecutionTime = ini_get('max_execution_time');

        // If the current max execution time is lower than our desired time, or if it's set to 0 (unlimited),
        // we set it to our desired time plus a buffer
        if ($currentMaxExecutionTime < $this->maxExecutionTime || $currentMaxExecutionTime == 0) {
            // Add a buffer of 30 seconds to our max execution time
            $newMaxExecutionTime = $this->maxExecutionTime + 30;
            
            // Try to set the new max execution time
            if (@ini_set('max_execution_time', $newMaxExecutionTime) === false) {
                $this->log("Warning: Unable to set max_execution_time to $newMaxExecutionTime seconds");
            } else {
                $this->log("Set max_execution_time to $newMaxExecutionTime seconds");
            }
        }

        // Disable the time limit for the script execution
        set_time_limit(0);
    }
    private function loadState()
    {
        // Check if state file exists and load the previous state
        if (file_exists($this->stateFile))
        {
            $stateData = file_get_contents($this->stateFile);
            $state = unserialize($stateData);
            if (isset($state['time_performed']))
            {
                $this->time_performed = $state['time_performed'];
            }
        }
    }

    private function saveState()
    {
        // Save the current state to the state file
        $state = ['time_performed' => $this->time_performed];
        file_put_contents($this->stateFile, serialize($state));
    }

    public static function validateTimerString($timerString)
    {
        // Regular expression to match the timer string format, including intervals
        $pattern = '/^(\*|\d{4}|\*\/\d+)-(\*|\d{2}|\*\/\d+)-(\*|\d{2}|\*\/\d+) (\*|\d{2}|\*\/\d+):(\*|\d{2}|\*\/\d+):(\*|\d{2}|\*\/\d+)$/';

        if (!preg_match($pattern, $timerString, $matches))
        {
            return false;
        }

        // Additional checks for valid ranges
        for ($i = 1; $i <= 6; $i++)
        {
            if ($matches[$i] !== '*')
            {
                if (strpos($matches[$i], '/') !== false)
                {
                    // Handle interval format
                    list($_, $interval) = explode('/', $matches[$i]);
                    $value = intval($interval);
                }
                else
                {
                    $value = intval($matches[$i]);
                }

                switch ($i)
                {
                    case 1: // Year
                        if ($value < 1970 || $value > 2100)
                            return false;
                        break;
                    case 2: // Month
                        if ($value < 1 || $value > 12)
                            return false;
                        break;
                    case 3: // Day
                        if ($value < 1 || $value > 31)
                            return false;
                        break;
                    case 4: // Hour
                        if ($value < 0 || $value > 23)
                            return false;
                        break;
                    case 5: // Minute
                    case 6: // Second
                        if ($value < 0 || $value > 59)
                            return false;
                        break;
                }
            }
        }

        return true;
    }

    private function log($str)
    {
        if ($this->debug)
        {
            $str = "[Istance " . $this->istanceId . "] $str";
            file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR."cronlog.log", "\n" . FN_Now() . "$str ", FILE_APPEND);
            echo "\n$str";
            // Flush the output buffer and close the connection to the client
            @ob_end_flush(); // Flush (send) the output buffer
            @flush(); // Force it to send            
        }
    }

    public function run()
    {

        if (empty($_GET[$this->istanceId]))
        {
            $this->log("first time " . FN_Now());
            $this->restartScript();
            return;
        }
        $this->log("no first time " . FN_Now());
        while (true)
        {
            $this->keepLoop();
            $callback = $this->callback;
            // Check if it's time to execute based on timerString
            if ($this->isTimeToExecute() === true)
            {
                if (function_exists($callback))
                {
                    $callback();
                }
                elseif ($this->isValidURL($callback))
                {
                    $this->callUrl($callback);
                }
            }
            usleep(500);
        }
    }

    private function isValidURL($string)
    {
        // Use filter_var to validate the URL
        return filter_var($string, FILTER_VALIDATE_URL) !== false;
    }

    private function keepLoop()
    {
        if (!$this->fileId)
        {
            $this->initializeLoop();
        }

        // Update the lock file timestamp to indicate the script is still running
        touch($this->lockFile);
        // Check remaining execution time
        $elapsedTime = time() - $this->startTime;
        $remainingTime = $this->maxExecutionTime - $elapsedTime;

        // Restart the script if the remaining time is less than the threshold
        if ($remainingTime < $this->restartThreshold)
        {
            $this->log("remainingTime < this->restartThreshold");
            $this->restartScript();
        }
    }

    private function initializeLoop()
    {
        // Ignore user abort to allow the script to continue running even if the client disconnects
        ignore_user_abort(true);

        // Start time of execution
        $this->startTime = time();
        $this->fileId = md5(file_get_contents(__FILE__));

        // Register shutdown function to remove lock file on exit
        register_shutdown_function(function ()
        {
            if (file_exists($this->lockFile))
            {
                unlink($this->lockFile);
            }
        });

        $this->checkAndCreateLockFile();

        // Inform the client that the script has started
        $this->log("Script avviato " . FN_Now());
        // If using FastCGI, finish the request to close the connection properly
        if (function_exists('fastcgi_finish_request'))
        {
            fastcgi_finish_request();
        }
    }

    private function checkAndCreateLockFile()
    {
        if (file_exists($this->lockFile))
        {
            // Check the age of the lock file
            $lockFileAge = time() - filemtime($this->lockFile);
            if ($lockFileAge < $this->lockTimeout)
            {
                $this->log("Script is already running");
                exit("Script is already running.\n");
            }
            else
            {
                // Remove the stale lock file
                unlink($this->lockFile);
            }
        }
        // Create a lock file to indicate that the script is running
        file_put_contents($this->lockFile, $this->fileId);
    }

    private function restartScript()
    {
        // Save the state before restarting
        $this->saveState();

        // Remove the lock file before restarting
        @unlink($this->lockFile);
        // Get the current script URL
        $scriptUrl = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        if (empty($_GET[$this->istanceId]))
        {
            $scriptUrl .= "&{$this->istanceId}=1";
        }
        $this->log("Restarting script: $scriptUrl");
        $this->callUrl($scriptUrl);
        if (empty($_GET[$this->istanceId]))
        {
            return;
        }
        exit("Restarting script: $scriptUrl \n");
    }

    private function callUrl($scriptUrl)
    {
        // Initialize cURL session
        $ch = curl_init($scriptUrl);
        // Set cURL options for a non-blocking request
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Don't wait for the response
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000); // Set a very short timeout
        // Execute the request
        curl_exec($ch);
        // Close cURL session
        curl_close($ch);
    }

    private function isTimeToExecute()
    {
        $currentTime = time();
        $parts = explode(' ', $this->timerString);
        $dateParts = explode('-', $parts[0]);
        $timeParts = explode(':', $parts[1]);

        // Check if each part matches the current time
        $match = true;
        $partPos = "";
        for ($i = 0; $i < 6; $i++)
        {
            $part = $i < 3 ? $dateParts[$i] : $timeParts[$i - 3];
            $currentValue = date(['Y', 'm', 'd', 'H', 'i', 's'][$i], $currentTime);

            if ($part != '*')
            {
                if (strpos($part, '/') !== false)
                {
                    $partPos = $i;
                    // Handle interval
                    list($_, $interval) = explode('/', $part);
                    if (intval($currentValue) % intval($interval) !== 0)
                    {
                        $match = false;
                        break;
                    }
                }
                elseif ($part != $currentValue)
                {
                    $match = false;
                    break;
                }
            }
        }

        //$i 0 is year
        //$i 1 is month
        //$i 2 is day
        //$i 3 is hour
        //$i 4 is minutes
        //$i 5 is seconds
        $div = 1;
        if ($partPos == 5)
        {
            $div = 1;
        }
        elseif ($partPos == 4)
        {
            $div = 60;
        }
        elseif ($partPos == 3)
        {
            $div = 3600;
        }
        elseif ($partPos == 2)
        {
            $div = 86400;
        }
        elseif ($partPos == 1)
        {
            $div = 2592000;
        }
        elseif ($partPos == 0)
        {
            $div = 31536000;
        }

        if ($match)
        {
            if ($this->time_performed === false)
            {
                $this->time_performed = $currentTime;
                $match = true;
            }
            elseif (intval($currentTime / $div) == intval($this->time_performed / $div))
            {
                $match = false;
            }
            else
            {
                $this->time_performed = $currentTime;
                $match = true;
            }
        }
        $this->saveState();
        return $match;
    }
}
