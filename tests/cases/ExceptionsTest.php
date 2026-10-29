<?php

namespace ntentan\mvc\tests\cases;

use ntentan\exceptions\NtentanException;
use ntentan\mvc\exceptions\ControllerActionNotFoundException;
use ntentan\mvc\exceptions\ControllerNotFoundException;
use ntentan\mvc\exceptions\ParameterNotFoundException;
use ntentan\panie\exceptions\ResolutionException;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{
    public function testControllerNotFoundException(): void
    {
        $previous = new \Exception('Previous failure');
        $exception = new ControllerNotFoundException('Controller missing', 0, $previous);

        $this->assertInstanceOf(NtentanException::class, $exception);
        $this->assertSame('Controller missing', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testControllerActionNotFoundException(): void
    {
        $exception = new ControllerActionNotFoundException('Action missing');

        $this->assertInstanceOf(NtentanException::class, $exception);
        $this->assertSame('Action missing', $exception->getMessage());
    }

    public function testParameterNotFoundException(): void
    {
        $sampleClosure = function (string $userId) {};
        $reflectionFunc = new \ReflectionFunction($sampleClosure);
        $parameter = $reflectionFunc->getParameters()[0];
        $resolutionException = new ResolutionException('Could not resolve userId');

        $exception = new ParameterNotFoundException($parameter, $resolutionException);

        $this->assertInstanceOf(NtentanException::class, $exception);
        $this->assertSame('Could not find a value to bind to [$userId]', $exception->getMessage());
        $this->assertSame($resolutionException, $exception->getPrevious());
        $this->assertSame(
            'A value for the parameter was not found. This most likely caused by a missing value in the requested route',
            $exception->getDetailedDescription()
        );
    }
}

