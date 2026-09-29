<?php
namespace AdmHistory\Tests;

use AdmHistory\MembershipTypeConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests für das Lesen und Prüfen der Konfiguration der Mitgliedsarten.
 */
final class MembershipTypeConfigTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function validConfig(): array
    {
        return [
            'types' => [
                'A' => ['name' => 'Aktiv',  'color' => '#cfe2ff', 'roles' => ['Aktiv', 'Bootsbeitrag']],
                'J' => ['name' => 'Jugend', 'color' => 'green',   'roles' => 'Jugend'],
            ],
            'commonRoles'  => ['Mitglied'],
            'historyYears' => 10,
        ];
    }

    public function testReadsTypesInConfiguredOrder(): void
    {
        $config = new MembershipTypeConfig(self::validConfig());

        self::assertSame(['A', 'J'], array_keys($config->getTypes()));
        $aktiv = $config->getType('A');
        $jugend = $config->getType('J');
        self::assertNotNull($aktiv);
        self::assertNotNull($jugend);
        self::assertSame('Aktiv', $aktiv->name);
        self::assertSame(['Aktiv', 'Bootsbeitrag'], $aktiv->roleNames);
        self::assertSame('#cfe2ff', $aktiv->color);
        self::assertSame(['Jugend'], $jugend->roleNames);
        self::assertSame('A (Aktiv)', $aktiv->label());
        self::assertNull($config->getType('X'));
        self::assertSame(0, $config->getPosition('A'));
        self::assertSame(1, $config->getPosition('J'));
        self::assertSame(PHP_INT_MAX, $config->getPosition('X'));
    }

    public function testRoleListsAndLookup(): void
    {
        $config = new MembershipTypeConfig(self::validConfig());

        self::assertSame(['Aktiv', 'Bootsbeitrag', 'Jugend'], $config->getTypeRoleNames());
        self::assertSame(['Mitglied'], $config->getCommonRoles());
        self::assertSame(['Aktiv', 'Bootsbeitrag', 'Jugend', 'Mitglied'], $config->getAllRoleNames());
        self::assertSame('A', $config->getTypeKeyForRole('bootsbeitrag'));
        self::assertNull($config->getTypeKeyForRole('Mitglied'));
        self::assertSame(10, $config->getHistoryYears());
    }

    public function testDefaultsWithoutOptionalKeys(): void
    {
        $config = new MembershipTypeConfig(['types' => ['P' => ['name' => 'Passiv', 'color' => '#eee', 'roles' => ['Passiv']]]]);

        self::assertSame([], $config->getCommonRoles());
        self::assertSame(15, $config->getHistoryYears());
    }

    public function testShippedConfigurationFileIsValid(): void
    {
        $config = MembershipTypeConfig::fromFile(dirname(__DIR__) . '/mitgliedsarten.php');

        self::assertSame(['A', 'E', 'F', 'J', 'P'], array_keys($config->getTypes()));
        self::assertSame(['Mitglied'], $config->getCommonRoles());
        self::assertTrue($config->isTransitionAllowed('J', 'A'));
        self::assertTrue($config->isTransitionAllowed('A', ''));
        self::assertFalse($config->isTransitionAllowed('A', 'J'));
    }

    public function testWithoutTransitionsEveryChangeIsAllowed(): void
    {
        $config = new MembershipTypeConfig(self::validConfig());

        self::assertSame(['', 'J'], $config->getAllowedTargets('A'));
        self::assertSame(['A', 'J'], $config->getAllowedTargets(''));
        self::assertTrue($config->isTransitionAllowed('A', ''));
        self::assertTrue($config->isTransitionAllowed('', 'J'));
        self::assertSame(['' => ['A', 'J'], 'A' => ['', 'J'], 'J' => ['', 'A']], $config->getAllTransitions());
    }

    public function testTransitionsRestrictTargetsAndKeepConfiguredOrder(): void
    {
        $config = new MembershipTypeConfig(array_merge(self::validConfig(), ['transitions' => [
            ''  => 'J',            // einzelnes Ziel als Text
            'J' => ['', 'A', 'J'], // eigenes Kürzel wird ignoriert
            'A' => '*',            // alle Ziele
        ]]));

        self::assertSame(['J'], $config->getAllowedTargets(''));
        self::assertSame(['', 'A'], $config->getAllowedTargets('J'));
        self::assertSame(['', 'J'], $config->getAllowedTargets('A'));
        self::assertTrue($config->isTransitionAllowed('J', 'J'));
        self::assertTrue($config->isTransitionAllowed('J', ''));
        self::assertFalse($config->isTransitionAllowed('', 'A'));
    }

    public function testUnlistedSourceAllowsNoChangeWhenTransitionsConfigured(): void
    {
        $config = new MembershipTypeConfig(array_merge(self::validConfig(), ['transitions' => ['J' => ['A']]]));

        self::assertSame([], $config->getAllowedTargets('A'));
        self::assertSame([], $config->getAllowedTargets(''));
        self::assertTrue($config->isTransitionAllowed('A', 'A'));
        self::assertFalse($config->isTransitionAllowed('A', ''));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidConfigs(): array
    {
        $base = self::validConfig();

        return [
            'keine Mitgliedsarten' => [['types' => []], '„types“'],
            'leeres Kürzel'        => [['types' => ['' => ['name' => 'X', 'color' => 'red', 'roles' => ['X']]]], 'Kürzel'],
            'zu langes Kürzel'     => [['types' => ['ABCD' => ['name' => 'X', 'color' => 'red', 'roles' => ['X']]]], 'Kürzel'],
            'ohne Name'            => [['types' => ['A' => ['color' => 'red', 'roles' => ['X']]]], 'keinen Namen'],
            'ungültige Farbe'      => [['types' => ['A' => ['name' => 'X', 'color' => '#zzz', 'roles' => ['X']]]], 'Farbe'],
            'ohne Rollen'          => [['types' => ['A' => ['name' => 'X', 'color' => 'red', 'roles' => []]]], 'keine Rollen'],
            'leere Rolle'          => [['types' => ['A' => ['name' => 'X', 'color' => 'red', 'roles' => ['']]]], 'nicht-leere Texte'],
            'Rolle doppelt'        => [['types' => [
                'A' => ['name' => 'X', 'color' => 'red', 'roles' => ['Aktiv']],
                'B' => ['name' => 'Y', 'color' => 'red', 'roles' => ['aktiv']],
            ]], 'mehreren Mitgliedsarten'],
            'gemeinsame Rolle doppelt' => [array_merge($base, ['commonRoles' => ['Aktiv']]), 'gemeinsame Rolle'],
            'historyYears negativ'     => [array_merge($base, ['historyYears' => -1]), 'historyYears'],
            'historyYears kein int'    => [array_merge($base, ['historyYears' => '10']), 'historyYears'],
            'transitions kein Array'   => [array_merge($base, ['transitions' => 'A']), '„transitions“'],
            'transitions unbekannter Ausgang' => [array_merge($base, ['transitions' => ['X' => ['A']]]), 'unbekannte Kürzel „X“'],
            'transitions unbekanntes Ziel'    => [array_merge($base, ['transitions' => ['A' => ['X']]]), 'unbekannte Kürzel „X“'],
            'transitions Ziel kein Text'      => [array_merge($base, ['transitions' => ['A' => [1]]]), 'müssen Kürzel sein'],
        ];
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('invalidConfigs')]
    public function testRejectsInvalidConfiguration(array $config, string $expectedMessagePart): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        new MembershipTypeConfig(array_replace(['commonRoles' => []], $config));
    }

    public function testFromFileRequiresExistingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fehlt');

        MembershipTypeConfig::fromFile(__DIR__ . '/gibt_es_nicht.php');
    }
}
