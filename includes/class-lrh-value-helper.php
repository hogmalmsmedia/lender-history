<?php
/**
 * Value Helper - Centraliserad hantering av värden och beräkningar
 * Placera denna fil i /includes/ mappen
 */

if (!defined('ABSPATH')) {
    exit;
}

class LRH_Value_Helper {
    
    /**
     * Normalisera ett värde för databas-lagring
     * Hanterar komma som decimal, mellanslag, procent-tecken etc
     * 
     * @param mixed $value Värdet att normalisera
     * @return float|null Normaliserat värde eller null
     */
    public static function normalize_value($value) {
        // Hantera tomma värden
        if ($value === '' || $value === null || $value === false || $value === '-' || $value === 'N/A') {
            return null;
        }
        
        // Konvertera till sträng för bearbetning
        $value = (string) $value;
        
        // Ta bort whitespace
        $value = trim($value);
        
        // Hantera svenska decimaltecken (komma till punkt)
        $value = str_replace(',', '.', $value);
        
        // Ta bort mellanslag (används ibland som tusentalsavgränsare)
        $value = str_replace(' ', '', $value);
        
        // Ta bort procenttecken
        $value = str_replace('%', '', $value);
        
        // Ta bort andra vanliga tecken
        $value = str_replace(['kr', 'SEK', ':-'], '', $value);
        
        // Konvertera till float
        $float_value = floatval($value);
        
        // Returnera null om värdet inte är numeriskt
        if (!is_numeric($value)) {
            return null;
        }
        
        return $float_value;
    }
    
    /**
     * Formatera värde för visning (svensk formatering)
     * 
     * @param float $value Värdet att formatera
     * @param int $decimals Antal decimaler
     * @param bool $include_percent Inkludera procenttecken
     * @return string Formaterat värde
     */
    public static function format_value($value, $decimals = 2, $include_percent = true) {
        if ($value === null || $value === '') {
            return '-';
        }
        
        $formatted = number_format(floatval($value), $decimals, ',', ' ');
        
        if ($include_percent) {
            $formatted .= '%';
        }
        
        return $formatted;
    }
    
    /**
     * Beräkna förändring mellan två värden
     * Returnerar förändring i procentenheter (inte procentuell förändring)
     * 
     * @param float|null $old_value Gamla värdet
     * @param float $new_value Nya värdet
     * @return array Array med change_amount, change_percentage, change_type
     */
    public static function calculate_change($old_value, $new_value) {
        // Normalisera värden
        $new_value = self::normalize_value($new_value);
        
        if ($new_value === null) {
            return [
                'change_amount' => null,
                'change_percentage' => null,
                'change_type' => 'invalid'
            ];
        }
        
        // Hantera initial värde (inget gammalt värde)
        if ($old_value === null || $old_value === '') {
            return [
                'change_amount' => null,
                'change_percentage' => null,
                'change_type' => 'initial'
            ];
        }
        
        $old_value = self::normalize_value($old_value);
        
        if ($old_value === null) {
            return [
                'change_amount' => null,
                'change_percentage' => null,
                'change_type' => 'initial'
            ];
        }
        
        // Beräkna förändring i procentenheter
        $change_amount = $new_value - $old_value;
        
        // För räntor använder vi procentenheter, inte procentuell förändring
        // Exempel: 3.5% -> 3.0% = -0.5 procentenheter (inte -14.3% förändring)
        $change_percentage = $change_amount;
        
        return [
            'change_amount' => $change_amount,
            'change_percentage' => $change_percentage,
            'change_type' => 'update'
        ];
    }
    
    /**
     * Formatera förändring för visning
     * 
     * @param float $change_amount Förändring i procentenheter
     * @return array Array med formatted, arrow, class
     */
    public static function format_change($change_amount) {
        if ($change_amount === null || abs($change_amount) < 0.001) {
            return [
                'formatted' => '0%',
                'arrow' => '→',
                'class' => 'lrh-no-change',
                'color' => '#6b7280'
            ];
        }
        
        $formatted = number_format(abs($change_amount), 2, ',', ' ');
        
        if ($change_amount > 0) {
            return [
                'formatted' => '+' . $formatted . '%',
                'arrow' => '↑',
                'class' => 'lrh-increase',
                'color' => '#dc2626'
            ];
        } else {
            return [
                'formatted' => '-' . $formatted . '%',
                'arrow' => '↓',
                'class' => 'lrh-decrease',
                'color' => '#059669'
            ];
        }
    }
    
    /**
     * Kontrollera om ett värde har ändrats
     * 
     * @param mixed $old_value Gamla värdet
     * @param mixed $new_value Nya värdet
     * @param float $epsilon Tolerans för jämförelse
     * @return bool True om värdet har ändrats
     */
    public static function has_value_changed($old_value, $new_value, $epsilon = 0.0001) {
        $old_normalized = self::normalize_value($old_value);
        $new_normalized = self::normalize_value($new_value);
        
        // Om båda är null, ingen förändring
        if ($old_normalized === null && $new_normalized === null) {
            return false;
        }
        
        // Om en är null och den andra inte, det är en förändring
        if ($old_normalized === null || $new_normalized === null) {
            return true;
        }
        
        // Jämför med epsilon för flyttal
        return abs($old_normalized - $new_normalized) > $epsilon;
    }
    
    /**
     * Validera om ett värde är inom rimliga gränser
     * 
     * @param float $value Värdet att validera
     * @param float $min Minvärde
     * @param float $max Maxvärde
     * @return bool True om värdet är giltigt
     */
    public static function validate_range($value, $min = -100, $max = 100) {
        $normalized = self::normalize_value($value);
        
        if ($normalized === null) {
            return false;
        }
        
        return $normalized >= $min && $normalized <= $max;
    }
    
    /**
     * Kontrollera om en förändring är stor (behöver validering)
     * 
     * @param float $change_amount Förändring i procentenheter
     * @param float $threshold Tröskelvärde i procentenheter
     * @return bool True om förändringen är stor
     */
    public static function is_large_change($change_amount, $threshold = null) {
        if ($change_amount === null) {
            return false;
        }
        
        if ($threshold === null) {
            $settings = get_option('lrh_settings', []);
            $threshold = isset($settings['large_change_threshold']) ? $settings['large_change_threshold'] : 25;
        }
        
        // Konvertera threshold från procent till procentenheter om det behövs
        // (25% förändring = 0.25 procentenheter för räntor som vanligtvis är 1-5%)
        if ($threshold > 10) {
            // Antagligen angett i procent av värdet, konvertera
            $threshold = $threshold / 100 * 5; // Anta genomsnittlig ränta på 5%
        }
        
        return abs($change_amount) > $threshold;
    }
    
    /**
     * Parsa ett värde från ACF-fält
     * Hanterar olika format som ACF kan returnera
     * 
     * @param mixed $acf_value Värde från ACF
     * @return float|null Normaliserat värde
     */
    public static function parse_acf_value($acf_value) {
        // Om det är en array (t.ex. från select field)
        if (is_array($acf_value)) {
            if (isset($acf_value['value'])) {
                $acf_value = $acf_value['value'];
            } elseif (isset($acf_value[0])) {
                $acf_value = $acf_value[0];
            } else {
                return null;
            }
        }
        
        // Om det är ett objekt
        if (is_object($acf_value)) {
            if (isset($acf_value->value)) {
                $acf_value = $acf_value->value;
            } else {
                $acf_value = (string) $acf_value;
            }
        }
        
        return self::normalize_value($acf_value);
    }
    
    /**
     * Batch-normalisera flera värden
     * 
     * @param array $values Array med värden
     * @return array Array med normaliserade värden
     */
    public static function normalize_batch($values) {
        return array_map([self::class, 'normalize_value'], $values);
    }
    
    /**
     * Generera en förändringsbeskrivning för tooltip/visning
     * 
     * @param float $old_value Gamla värdet
     * @param float $new_value Nya värdet
     * @param string $date Datum för ändring
     * @return string Beskrivning av förändringen
     */
    public static function get_change_description($old_value, $new_value, $date = null) {
        $change_data = self::calculate_change($old_value, $new_value);
        
        if ($change_data['change_type'] === 'initial') {
            return sprintf(
                'Initialt värde: %s',
                self::format_value($new_value)
            );
        }
        
        $change_info = self::format_change($change_data['change_amount']);
        
        $description = sprintf(
            'Från %s till %s (%s)',
            self::format_value($old_value),
            self::format_value($new_value),
            $change_info['formatted']
        );
        
        if ($date) {
            $description .= ' - ' . date_i18n('j F, Y', strtotime($date));
        }
        
        return $description;
    }
}