#!/bin/bash

# Script per fixare i campi translatable null nei modelli
# Questo script risolve il problema con HasTranslations trait che fallisce quando i campi sono null

set -e

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_step() {
    echo -e "${BLUE}➜${NC} $1"
}

print_success() {
    echo -e "${GREEN}✅${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠️${NC} $1"
}

print_error() {
    echo -e "${RED}❌${NC} $1"
}

print_step "=== FIX CAMPI TRANSLATABLE NULL ==="

# Verifica che siamo nel container Docker
if [ ! -f "/var/www/html/osm2cai2/.env" ]; then
    print_error "Questo script deve essere eseguito dal container Docker!"
    exit 1
fi

cd /var/www/html/osm2cai2

print_step "Analisi modelli con campi translatable..."

# Array dei modelli da controllare e fixare
MODELS=(
    "EcPoi"
    "HikingRoute" 
    "Layer"
)

# Array dei campi translatable per ogni modello
declare -A TRANSLATABLE_FIELDS
TRANSLATABLE_FIELDS["EcPoi"]="properties"
TRANSLATABLE_FIELDS["HikingRoute"]="properties"
TRANSLATABLE_FIELDS["Layer"]="properties"

print_step "Modelli da processare: ${MODELS[*]}"

for model in "${MODELS[@]}"; do
    print_step "=== PROCESSING: $model ==="
    
    # Conta record con campi null
    print_step "Contando record con campi translatable null..."
    
    # Query per contare record con properties null
    null_count=$(php artisan tinker --execute="
        \$count = App\Models\\$model::whereNull('properties')->count();
        echo \$count;
    " 2>/dev/null | grep -E '^[0-9]+$' | tail -n 1)
    
    if [ "$null_count" -gt 0 ]; then
        print_warning "Trovati $null_count record con properties null in $model"
        
        # Fix dei record con properties null
        print_step "Fixing record con properties null..."
        
        php artisan tinker --execute="
            \$updated = App\Models\\$model::whereNull('properties')
                ->update(['properties' => '[]']);
            echo 'Updated ' . \$updated . ' records';
        " 2>/dev/null
        
        print_success "Fixed $null_count record in $model"
    else
        print_success "Nessun record con properties null trovato in $model"
    fi
    
    # Conta record con properties come stringa vuota
    empty_count=$(php artisan tinker --execute="
        \$count = App\Models\\$model::where('properties', '')->count();
        echo \$count;
    " 2>/dev/null | grep -E '^[0-9]+$' | tail -n 1)
    
    if [ -n "$empty_count" ] && [ "$empty_count" -gt 0 ]; then
        print_warning "Trovati $empty_count record con properties vuoti in $model"
        
        # Fix dei record con properties vuoti
        print_step "Fixing record con properties vuoti..."
        
        php artisan tinker --execute="
            \$updated = App\Models\\$model::where('properties', '')
                ->update(['properties' => '[]']);
            echo 'Updated ' . \$updated . ' records';
        " 2>/dev/null
        
        print_success "Fixed $empty_count record in $model"
    else
        print_success "Nessun record con properties vuoti trovato in $model"
    fi

    # Fix dei campi translatable nested null
    print_step "Fixing campi translatable nested null..."
    
    php artisan tinker --execute="
        echo 'Fixing nested translatable fields in $model...';
        \$updated = 0;
        \$batchSize = 1000;
        \$offset = 0;
        
        do {
            \$records = App\Models\\$model::whereNotNull('properties')->offset(\$offset)->limit(\$batchSize)->get();
            \$count = \$records->count();
            
            foreach(\$records as \$record) {
                \$properties = \$record->properties;
                \$changed = false;
                
                // Fix properties->description se è null
                if (isset(\$properties['description']) && is_null(\$properties['description'])) {
                    \$properties['description'] = [];
                    \$changed = true;
                }
                
                // Fix properties->excerpt se è null
                if (isset(\$properties['excerpt']) && is_null(\$properties['excerpt'])) {
                    \$properties['excerpt'] = [];
                    \$changed = true;
                }
                
                // Fix properties->difficulty se è null (solo per EcTrack/HikingRoute)
                if (isset(\$properties['difficulty']) && is_null(\$properties['difficulty'])) {
                    \$properties['difficulty'] = [];
                    \$changed = true;
                }
                
                // Fix properties->title se è null (solo per Layer)
                if (isset(\$properties['title']) && is_null(\$properties['title'])) {
                    \$properties['title'] = [];
                    \$changed = true;
                }
                
                // Fix properties->subtitle se è null (solo per Layer)
                if (isset(\$properties['subtitle']) && is_null(\$properties['subtitle'])) {
                    \$properties['subtitle'] = [];
                    \$changed = true;
                }
                
                if (\$changed) {
                    \$record->properties = \$properties;
                    \$record->save();
                    \$updated++;
                }
            }
            
            \$offset += \$batchSize;
        } while (\$count == \$batchSize);
        
        echo 'Updated ' . \$updated . ' records with nested null fields';
    " 2>/dev/null
    
    print_success "Fixed nested translatable fields in $model"
done

print_step "=== VERIFICA FINALE ==="

# Verifica finale che non ci siano più record con properties null
total_null=0
for model in "${MODELS[@]}"; do
    null_count=$(php artisan tinker --execute="
        \$count = App\Models\\$model::whereNull('properties')->count();
        echo \$count;
    " 2>/dev/null | grep -E '^[0-9]+$' | tail -n 1)
    
    if [ -n "$null_count" ] && [ "$null_count" -gt 0 ]; then
        print_error "ATTENZIONE: Ancora $null_count record con properties null in $model"
        total_null=$((total_null + null_count))
    else
        print_success "$model: Nessun record con properties null"
    fi
done

if [ "$total_null" -eq 0 ]; then
    print_success "🎉 TUTTI I CAMPI TRANSLATABLE SONO STATI FIXATI CON SUCCESSO!"
else
    print_error "❌ ATTENZIONE: Ancora $total_null record con properties null nel totale"
    exit 1
fi

print_step "=== FIX COMPLETATO ==="
print_step "I modelli ora dovrebbero funzionare correttamente con Nova e HasTranslations"
