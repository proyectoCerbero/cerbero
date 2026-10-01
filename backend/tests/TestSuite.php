<?php

class TestSuite
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function run(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            echo "[OK] $name" . PHP_EOL;
        } catch (Throwable $error) {
            $this->failed++;
            $this->failures[] = "$name: {$error->getMessage()}";
            echo "[FALLO] $name" . PHP_EOL;
        }
    }

    public function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public function assertFalse(bool $condition, string $message): void
    {
        $this->assertTrue(!$condition, $message);
    }

    public function assertSame($expected, $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(sprintf(
                '%s (esperado: %s; recibido: %s)',
                $message,
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    public function finish(): int
    {
        echo PHP_EOL . "Resultado: {$this->passed} pruebas correctas, {$this->failed} con fallos." . PHP_EOL;
        foreach ($this->failures as $failure) {
            fwrite(STDERR, "- $failure" . PHP_EOL);
        }
        return $this->failed === 0 ? 0 : 1;
    }
}
