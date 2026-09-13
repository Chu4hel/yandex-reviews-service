<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contracts\YandexParserInterface;
use App\Infrastructure\Services\YandexMapsParserService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ArchitectureTest extends TestCase
{
    /**
     * Domain layer must NEVER depend on Infrastructure, HTTP layer, or Eloquent models.
     */
    public function test_domain_layer_does_not_depend_on_infrastructure_or_http(): void
    {
        $domainPath = dirname(__DIR__, 2).'/app/Domain';
        $domainFiles = $this->getPhpFiles($domainPath);

        $forbiddenKeywords = [
            'App\Infrastructure',
            'App\Http',
            'Illuminate\Database\Eloquent\Model',
            'Illuminate\Http\Request',
            'Illuminate\Support\Facades\Http',
        ];

        foreach ($domainFiles as $file) {
            $content = (string) file_get_contents($file);

            foreach ($forbiddenKeywords as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $content,
                    "Нарушение Dependency Inversion: файл [{$file}] импортирует запрещенную зависимость [{$forbidden}]."
                );
            }
        }
    }

    /**
     * All Domain DTOs must be strictly typed and have readonly properties.
     */
    public function test_all_dtos_have_readonly_properties_and_strict_types(): void
    {
        $dtoPath = dirname(__DIR__, 2).'/app/Domain/DTO';
        $dtoFiles = $this->getPhpFiles($dtoPath);

        foreach ($dtoFiles as $file) {
            $content = (string) file_get_contents($file);

            $this->assertStringContainsString(
                'declare(strict_types=1);',
                $content,
                "Файл DTO [{$file}] должен содержать declare(strict_types=1);"
            );

            $className = 'App\\Domain\\DTO\\'.basename($file, '.php');
            $this->assertTrue(class_exists($className), "Класс {$className} не найден");

            $reflection = new ReflectionClass($className);
            $constructor = $reflection->getConstructor();
            $this->assertNotNull($constructor, "DTO {$className} должен иметь конструктор");

            foreach ($constructor->getParameters() as $param) {
                $this->assertTrue($param->isPromoted(), "Параметр [{$param->getName()}] в DTO [{$className}] должен быть promoted свойством");
                $prop = $reflection->getProperty($param->getName());
                $this->assertTrue(
                    $reflection->isReadOnly() || $prop->isReadOnly(),
                    "Свойство [{$param->getName()}] в DTO [{$className}] должно быть readonly"
                );
            }
        }
    }

    /**
     * Infrastructure parser must implement domain contract.
     */
    public function test_parser_implements_domain_contract(): void
    {
        $interfaces = class_implements(YandexMapsParserService::class);
        $this->assertIsArray($interfaces);
        $this->assertArrayHasKey(
            YandexParserInterface::class,
            $interfaces,
            'YandexMapsParserService должен реализовывать интерфейс YandexParserInterface'
        );
    }

    /**
     * @return array<int, string>
     */
    private function getPhpFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }
}
