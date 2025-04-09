<?php

namespace Lomkit\Access\Tests\Unit\Console;

use Lomkit\Access\Tests\Unit\TestCase;

class MakeCommandsTest extends TestCase
{
    public function test_make_plain_perimeter_command()
    {
        @unlink(app_path('Access/Perimeters/Perimeter.php'));

        $this
            ->artisan('make:perimeter', ['name' => 'Perimeter'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Access/Perimeters/Perimeter.php'));
        $this->assertStringContainsString('class Perimeter extends Perimeter', file_get_contents(app_path('Access/Perimeters/Perimeter.php')));

        unlink(app_path('Access/Perimeters/Perimeter.php'));
    }

    public function test_make_overlay_perimeter_command()
    {
        @unlink(app_path('Access/Perimeters/Perimeter.php'));

        $this
            ->artisan('make:perimeter', ['name' => 'Perimeter', '--overlay' => true])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Access/Perimeters/Perimeter.php'));
        $this->assertStringContainsString('class Perimeter extends OverlayPerimeter', file_get_contents(app_path('Access/Perimeters/Perimeter.php')));

        unlink(app_path('Access/Perimeters/Perimeter.php'));
    }

    public function test_make_control_command()
    {
        @unlink(app_path('Access/Controls/TestControl.php'));

        $this
            ->artisan('make:control', ['name' => 'TestControl'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Access/Controls/TestControl.php'));
        $this->assertStringContainsString('class TestControl', file_get_contents(app_path('Access/Controls/TestControl.php')));

        unlink(app_path('Access/Controls/TestControl.php'));
    }

    public function test_make_control_with_base_control_command()
    {
        @unlink(app_path('Access/Controls/TestControl.php'));
        @unlink(app_path('Access/Controls/Control.php'));

        file_put_contents(app_path('Access/Controls/Control.php'), '');

        $this
            ->artisan('make:control', ['name' => 'TestControl'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Access/Controls/TestControl.php'));
        $this->assertStringContainsString('class TestControl extends Control', file_get_contents(app_path('Access/Controls/TestControl.php')));

        unlink(app_path('Access/Controls/TestControl.php'));
        unlink(app_path('Access/Controls/Control.php'));
    }

    public function test_make_control_with_perimeters_command()
    {
        @unlink(app_path('Access/Controls/TestControl.php'));
        @unlink(app_path('Access/Perimeters/TestPerimeter.php'));
        @unlink(app_path('Access/Perimeters/SecondTestPerimeter.php'));

        file_put_contents(app_path('Access/Perimeters/TestPerimeter.php'), '');
        file_put_contents(app_path('Access/Perimeters/SecondTestPerimeter.php'), '');

        $this
            ->artisan('make:control')
            ->expectsQuestion('What should the control be named?', 'TestControl')
            ->expectsChoice('What perimeters should this control apply to? (Optional)', ['TestPerimeter'], ['TestPerimeter', 'SecondTestPerimeter'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Access/Controls/TestControl.php'));
        $this->assertStringContainsString('class TestControl', file_get_contents(app_path('Access/Controls/TestControl.php')));
        $this->assertStringContainsString(app_path('Access/Perimeters/TestPerimeter.php').'::new()', file_get_contents(app_path('Access/Controls/TestControl.php')));

        unlink(app_path('Access/Perimeters/TestPerimeter.php'));
        unlink(app_path('Access/Perimeters/SecondTestPerimeter.php'));
        unlink(app_path('Access/Controls/TestControl.php'));
    }
}
