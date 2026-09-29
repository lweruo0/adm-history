<?php
namespace AdmHistory;

/**
 * Kleine Hilfsfunktionen für die HTML-Ausgabe.
 */
final class Html
{
    /** Escaped einen Text für die Ausgabe in HTML (Inhalt und Attributwerte). */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
