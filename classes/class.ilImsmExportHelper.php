<?php

/**
 * Helper-Klasse für ImsmExport Plugin
 * Stellt Hilfsfunktionen für CSV-Export bereit
 */
class ilImsmExportHelper
{
    /**
     * Bereitet Freitext-Eingaben für CSV-Export auf
     * 
     * @param string|null $userInput Nutzereingabe
     * @return string Bereinigte Eingabe für CSV
     */
    public static function sanitizeFreetextForCsv(?string $userInput): string
    {
        if ($userInput === null || $userInput === '') {
            return '';
        }
        
        // 0. UTF-8 Konsistenz sicherstellen
        $text = mb_convert_encoding($userInput, 'UTF-8', 'UTF-8');
        
        // 0.5 HTML-Tags entfernen
        $text = strip_tags($text);
        
        // 0.6 HTML-Entities dekodieren
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 1. Non-breaking Spaces und andere Whitespace characters normalisieren
        // Häufiges Problem bei Copy-Paste aus Word/PDF
        $text = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $text);
        
        // 2. Gefährliche Kontrollzeichen entfernen (inkl. Unicode-Invisibles)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);
        
        // 3. Zeilenendezeichen UND Tabs normalisieren
        $text = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $text);
        
        // 4. Mehrfache Leerzeichen reduzieren
        $text = preg_replace('/\s+/u', ' ', $text);
        
        // 5. Whitespace trimmen
        $text = trim($text);
        
        // 6. Formula Injection verhindern (Excel-Sicherheit)
        // Robuster Check – ignoriert führende Leerzeichen
        if ($text !== '' && preg_match('/^\s*[=+\-@]/u', $text)) {
            $text = "'" . $text;
        }
        
        // 7. CSV-Anführungszeichen escapen
        $text = str_replace('"', '""', $text);
        
        // 8. Länge begrenzen (UTF-8 safe, IMS-M Kompatibilität)
        if (mb_strlen($text, 'UTF-8') > 32000) {
            $text = mb_substr($text, 0, 32000, 'UTF-8') . '...';
        }
        
        return $text;
    }
}
