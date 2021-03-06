<?php

namespace Bugsnag {

    // Mock error_reporting global function, to be controlled by global switches in the tests
    function error_reporting()
    {
        global $mockErrorReporting, $mockErrorReportingLevel;
        if (isset($mockErrorReporting) && $mockErrorReporting == true) {
            return $mockErrorReportingLevel;
        } else {
            return call_user_func_array('\error_reporting', func_get_args());
        }
    }

}

namespace Bugsnag\Tests {

    use Bugsnag\Client;
    use Bugsnag\Configuration;
    use Bugsnag\Handler;
    use Exception;

    /**
     * @runTestsInSeparateProcesses
     */
    class HandlerTest extends TestCase
    {
        protected $client;

        protected function setUp()
        {
            global $mockErrorReporting, $mockErrorReportingLevel;
            $mockErrorReporting = false;
            $mockErrorReportingLevel = null;

            $this->client = $this->getMockBuilder(Client::class)
                                ->setMethods(['notify', 'flush'])
                                ->setConstructorArgs([new Configuration('example-api-key')])
                                ->getMock();
        }

        public function testErrorHandler()
        {
            $this->client->expects($this->once())->method('notify');

            Handler::register($this->client)->errorHandler(E_WARNING, 'Something broke', 'somefile.php', 123);
        }

        public function testErrorHandlerWithPrevious()
        {
            if (class_exists(\PHPUnit_Framework_Error_Warning::class)) {
                $this->expectedException(\PHPUnit_Framework_Error_Warning::class);
            } else {
                $this->expectedException(\PHPUnit\Framework\Error\Warning::class);
            }

            Handler::registerWithPrevious($this->client)->errorHandler(E_WARNING, 'Something broke', 'somefile.php', 123);
        }

        public function testExceptionHandler()
        {
            $this->client->expects($this->once())->method('notify');

            Handler::register($this->client)->exceptionHandler(new Exception('Something broke'));
        }

        public function testExceptionHandlerWithPrevious()
        {
            // Register a custom exception handler that stores it's parameter in the
            // parent's scope so we can assert that it was correctly called.
            $previous_exception_handler_arg = null;
            set_exception_handler(
                function ($e) use (&$previous_exception_handler_arg) {
                    $previous_exception_handler_arg = $e;
                }
            );

            $e_to_throw = new Exception('Something broke');

            Handler::registerWithPrevious($this->client)->exceptionHandler($e_to_throw);

            $this->assertSame($e_to_throw, $previous_exception_handler_arg);
        }

        public function testExceptionHandlerWithoutPrevious()
        {
            $previous_exception_handler_called = false;
            set_exception_handler(
                function ($e) use (&$previous_exception_handler_called) {
                    $previous_exception_handler_called = true;
                }
            );

            Handler::register($this->client)->exceptionHandler(new Exception());

            $this->assertFalse($previous_exception_handler_called);
        }

        public function testCustomErrorHandlerValueReturned()
        {
            set_error_handler(
                function () {
                    return '123';
                }
            );

            $this->assertSame(
                '123',
                Handler::registerWithPrevious($this->client)->errorHandler(E_WARNING, 'Something broke')
            );
        }

        public function testErrorReportingLevel()
        {
            $this->client->expects($this->once())->method('notify');

            $this->client->setErrorReportingLevel(E_NOTICE);

            Handler::register($this->client)->errorHandler(E_NOTICE, 'Something broke', 'somefile.php', 123);
        }

        public function testErrorReportingLevelFails()
        {
            $this->client->expects($this->never())->method('notify');

            $this->client->setErrorReportingLevel(E_NOTICE);

            Handler::register($this->client)->errorHandler(E_WARNING, 'Something broke', 'somefile.php', 123);
        }

        public function testErrorReportingWithoutNotice()
        {
            $this->client->expects($this->never())->method('notify');

            $this->client->setErrorReportingLevel(E_ALL & ~E_NOTICE);

            Handler::register($this->client)->errorHandler(E_NOTICE, 'Something broke', 'somefile.php', 123);
        }

        public function testErrorReportingDefault()
        {
            global $mockErrorReporting, $mockErrorReportingLevel;
            $mockErrorReporting = true;
            $mockErrorReportingLevel = E_NOTICE;

            $this->client->expects($this->once())->method('notify');

            Handler::register($this->client)->errorHandler(E_NOTICE, 'Something broke', 'somefile.php', 123);
        }

        public function testErrorReportingDefaultFails()
        {
            global $mockErrorReporting, $mockErrorReportingLevel;
            $mockErrorReporting = true;
            $mockErrorReportingLevel = E_NOTICE;

            $this->client->expects($this->never())->method('notify');

            Handler::register($this->client)->errorHandler(E_WARNING, 'Something broke', 'somefile.php', 123);
        }

        public function testErrorReportingSuppressed()
        {
            global $mockErrorReporting, $mockErrorReportingLevel;
            $mockErrorReporting = true;
            $mockErrorReportingLevel = 0;

            $this->client->setErrorReportingLevel(E_NOTICE);

            $this->client->expects($this->never())->method('notify');

            Handler::register($this->client)->errorHandler(E_NOTICE, 'Something broke', 'somefile.php', 123);
        }

        public function testErrorReportingDefaultSuppressed()
        {
            global $mockErrorReporting, $mockErrorReportingLevel;
            $mockErrorReporting = true;
            $mockErrorReportingLevel = 0;

            $this->client->expects($this->never())->method('notify');

            Handler::register($this->client)->errorHandler(E_NOTICE, 'Something broke', 'somefile.php', 123);
        }

        public function testCanShutdown()
        {
            $this->client->expects($this->never())->method('notify');
            $this->client->expects($this->once())->method('flush');

            Handler::register($this->client)->shutdownHandler();
        }

        public function testCanFatalShutdown()
        {
            $report = $this->getFunctionMock('Bugsnag', 'error_get_last');
            $report->expects($this->once())->will($this->returnValue(['type' => E_ERROR, 'message' => 'Undefined variable: a', 'file' => '/foo/index.php', 'line' => 2]));

            $this->client->expects($this->once())->method('notify');
            $this->client->expects($this->once())->method('flush');

            Handler::register($this->client)->shutdownHandler();
        }
    }
}
