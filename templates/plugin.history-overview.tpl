{* Kachel(n) der Mitglieder-Historie auf der Admidio-Übersichtsseite, siehe overview.php *}
{foreach $tiles as $tile}
<div id="plugin-{$name}-{$tile.key}" class="admidio-plugin-content">
    <h3><a href="{$historyUrl}" title="Mitglieder-Historie öffnen">Mitglieder {$tile.year}</a></h3>
    <p class="text-muted small mb-2">{$tile.label}: Personen in der Rolle am Stichtag</p>
    {if count($tile.rows) > 0}
        <table class="table table-sm mb-2">
            <thead>
                <tr>
                    <th>Rolle</th>
                    <th class="text-end">01.01.</th>
                    <th class="text-end">31.12.</th>
                </tr>
            </thead>
            <tbody>
                {foreach $tile.rows as $row}
                    <tr>
                        <td>{$row.label|escape}</td>
                        <td class="text-end">{$row.begin}</td>
                        <td class="text-end">{$row.end}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    {else}
        <p>Keine Rollen konfiguriert („overviewRoles“ in mitgliedsarten.php).</p>
    {/if}
</div>
{/foreach}
