<?php

namespace Lomkit\Access\Tests\Unit\Console;

use Lomkit\Access\Tests\Unit\TestCase;

class MakeCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        @unlink(app_path('Access/Perimeters/TestPerimeter.php'));
        @unlink(app_path('Access/Controls/TestControl.php'));
        @unlink(app_path('Access/Controls/Control.php'));
        @unlink(app_path('Access/Perimeters/TestPerimeter.php'));
        @unlink(app_path('Access/Perimeters/SecondTestPerimeter.php'));
        @unlink(app_path('Models/User.php'));
        @unlink(app_path('Models/Post.php'));
    }

    public function test_make_plain_perimeter_command()
    {
        $this
            ->artisan('make:perimeter', ['name' => 'TestPerimeter'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Access/Perimeters/TestPerimeter.php'));
        $this->assertStringContainsString('class TestPerimeter extends Perimeter', file_get_contents(app_path('Access/Perimeters/TestPerimeter.php')));

        unlink(app_path('Access/Perimeters/TestPerimeter.php'));
    }

    public function test_make_overlay_perimeter_command()
    {
        $this
            ->artisan('make:perimeter', ['name' => 'TestPerimeter', '--overlay' => true])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Access/Perimeters/TestPerimeter.php'));
        $this->assertStringContainsString('class TestPerimeter extends OverlayPerimeter', file_get_contents(app_path('Access/Perimeters/TestPerimeter.php')));

        unlink(app_path('Access/Perimeters/TestPerimeter.php'));
    }

    public function test_make_control_command()
    {
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
        $this->assertStringContainsString('App\\Access\\Perimeters\\TestPerimeter::new()', file_get_contents(app_path('Access/Controls/TestControl.php')));

        unlink(app_path('Access/Perimeters/TestPerimeter.php'));
        unlink(app_path('Access/Perimeters/SecondTestPerimeter.php'));
        unlink(app_path('Access/Controls/TestControl.php'));
    }

    public function test_make_control_with_model_command()
    {
        file_put_contents(app_path('Models/User.php'), '');
        file_put_contents(app_path('Models/Post.php'), '');

        $this
            ->artisan('make:control')
            ->expectsQuestion('What should the control be named?', 'TestControl')
            ->expectsChoice('What model should this control apply to? (Optional)', 'User', ['User', 'Post'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Access/Controls/TestControl.php'));
        $this->assertStringContainsString('class TestControl', file_get_contents(app_path('Access/Controls/TestControl.php')));
        $this->assertStringContainsString('protected string $model = \App\Models\User::class;', file_get_contents(app_path('Access/Controls/TestControl.php')));

        unlink(app_path('Models/User.php'));
        unlink(app_path('Models/Post.php'));
    }
}
