<?php

class ControlsTest extends \Lomkit\Access\Tests\TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_should_first_perimeter()
    {
        $controlMock = Mockery::mock(\Lomkit\Access\Tests\Support\Access\Controls\ModelControl::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();


        $controlMock->shouldReceive('shouldClient')->with()->once()->andReturn(true);
        $controlMock->shouldReceive('shouldSite')->with()->never();
        $controlMock->shouldReceive('shouldOwn')->with()->never();

        $this->assertTrue(
            $controlMock
                ->should(new \Lomkit\Access\Tests\Support\Access\Perimeters\ClientPerimeter)
        );
    }

    public function test_should_second_perimeter()
    {
        $controlMock = Mockery::mock(\Lomkit\Access\Tests\Support\Access\Controls\ModelControl::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();


        $controlMock->shouldReceive('shouldClient')->with()->never();
        $controlMock->shouldReceive('shouldSite')->with()->once()->andReturn(true);
        $controlMock->shouldReceive('shouldOwn')->with()->never();

        $this->assertTrue(
            $controlMock
                ->should(new \Lomkit\Access\Tests\Support\Access\Perimeters\SitePerimeter)
        );
    }

    public function test_should_third_perimeter()
    {
        $controlMock = Mockery::mock(\Lomkit\Access\Tests\Support\Access\Controls\ModelControl::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();


        $controlMock->shouldReceive('shouldClient')->with()->never();
        $controlMock->shouldReceive('shouldSite')->with()->never();
        $controlMock->shouldReceive('shouldOwn')->with()->once()->andReturn(true);

        $this->assertTrue(
            $controlMock
                ->should(new \Lomkit\Access\Tests\Support\Access\Perimeters\OwnPerimeter)
        );
    }
}