<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace Glpi\Tests;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

class TestProfiler implements Extension
{
    private static ?TestProfiler $instance = null;
    private array $sections = [];
    private const PROFILER_REPORT_FILE = __DIR__ . '/../test_profiler_report.txt';
    private const MIN_TIME_THRESHOLD = 0.25; // Number of seconds a test must take to be reported

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        $facade->registerSubscribers(
            new TestStartProfilerSubscriber(),
            new TestEndProfilerSubscriber(),
            new TestExecutionFinishedSubscriber()
        );
    }

    public static function getInstance(): ?TestProfiler
    {
        return self::$instance;
    }

    public function startTest(string $testName): void
    {
        $this->sections[$testName] = [microtime(true), null];
    }

    public function endTest(string $testName): void
    {
        if (isset($this->sections[$testName])) {
            $this->sections[$testName][1] = microtime(true);
        }
    }

    public function generateReport(): void
    {
        $report = "Test Profiler Report\n";
        $report .= "====================\n";
        // Sort sections so longest duration tests are first
        $sorted_sections = $this->sections;
        uasort($sorted_sections, static function ($a, $b) {
            $durationA = $a[1] !== null ? $a[1] - $a[0] : 0;
            $durationB = $b[1] !== null ? $b[1] - $b[0] : 0;
            return $durationB <=> $durationA; // Sort in descending order
        });
        // Filter out tests that took less than the threshold
        $sorted_sections = array_filter($sorted_sections, static function ($times) {
            if ($times[1] === null) {
                return false;
            }
            $duration = $times[1] - $times[0];
            return $duration >= self::MIN_TIME_THRESHOLD;
        });

        foreach ($sorted_sections as $testName => $times) {
            if ($times[1] !== null) {
                $duration = $times[1] - $times[0];
                $report .= sprintf("Test: %s, Duration: %.4f seconds\n", $testName, $duration);
            } else {
                $report .= sprintf("Test: %s, Duration: Not completed\n", $testName);
            }
        }
        if (!file_put_contents(self::PROFILER_REPORT_FILE, $report)) {
            echo "Failed to write report to " . self::PROFILER_REPORT_FILE . "\n";
        } else {
            echo "Report written to " . realpath(self::PROFILER_REPORT_FILE) . "\n";
        }
    }
}

class TestStartProfilerSubscriber implements PreparationStartedSubscriber
{
    public function notify(PreparationStarted $event): void
    {
        $name = $event->test()->isTestMethod()
            ? $event->test()->nameWithClass()
            : $event->test()->name();
        TestProfiler::getInstance()->startTest($name);
    }
}

class TestEndProfilerSubscriber implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        $name = $event->test()->isTestMethod()
            ? $event->test()->nameWithClass()
            : $event->test()->name();
        TestProfiler::getInstance()->endTest($name);
    }
}

class TestExecutionFinishedSubscriber implements \PHPUnit\Event\TestRunner\FinishedSubscriber
{
    public function notify(\PHPUnit\Event\TestRunner\Finished $event): void
    {
        echo "\nTest execution finished. Generating report...\n";
        TestProfiler::getInstance()->generateReport();
    }
}
