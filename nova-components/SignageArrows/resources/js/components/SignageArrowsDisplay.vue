<template>
    <div class="signage-arrows-container">
        <!-- Itera su ogni hiking route nel signage -->
        <div v-for="(routeData, routeId) in processedSignageData" :key="routeId" class="route-signage-block">

            <!-- Itera direttamente sulle arrows ordinate -->
            <template v-for="(arrow, arrowIdx) in (routeData.orderedArrows || [])" :key="arrowIdx">
                <div v-if="arrow && arrow.rows && Array.isArray(arrow.rows) && arrow.rows.length > 0"
                    class="signage-arrow-wrapper">
                    <!-- Pulsanti per riordinare la freccia -->
                    <div class="arrow-order-controls">
                        <button class="arrow-order-btn" :disabled="!canMoveUp(routeId, arrowIdx)"
                            @click="moveArrow(routeId, arrowIdx, 'up')" title="Sposta su">
                            ▲
                        </button>
                        <button class="arrow-order-btn" :disabled="!canMoveDown(routeId, arrowIdx)"
                            @click="moveArrow(routeId, arrowIdx, 'down')" title="Sposta giù">
                            ▼
                        </button>
                    </div>

                    <!-- Pulsante per invertire la direzione della singola freccia -->
                    <div class="arrow-direction-control">
                        <button @click="toggleArrowDirection(routeId, arrowIdx)" class="arrow-direction-btn"
                            :title="`Inverti direzione: ${arrow.direction === 'forward' ? 'forward → backward' : 'backward → forward'}`">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M3 12h18M3 12l4-4m-4 4l4 4M21 12l-4-4m4 4l-4 4" />
                            </svg>
                        </button>
                    </div>

                    <div class="signage-arrow" :class="getArrowDirection(routeId, arrowIdx, arrow.direction)">
                        <!-- Punta freccia sinistra per backward -->
                        <div v-if="getArrowDirection(routeId, arrowIdx, arrow.direction) === 'backward'"
                            class="arrow-point-left"></div>

                        <div class="arrow-body">
                            <div class="route-id-column">
                                <div class="route-id-red"></div>
                                <div class="route-id-box">{{ routeData?.ref }}</div>
                                <div class="route-id-red"></div>
                            </div>
                            <div class="destinations-list"
                                :class="{ 'destinations-list--single': (arrow.rows || []).length === 1 }">
                                <div v-for="(destination, idx) in arrow.rows"
                                    :key="idx"
                                    class="destination-row"
                                    :class="{ 'destination-row--with-separator': idx < arrow.rows.length - 1 }">
                                    <span class="destination-info">
                                        <span class="destination-name">{{ formatDestinationName(destination) }}</span>
                                        <span v-if="destination?.description" class="destination-description">{{
                                            destination?.description }}</span>
                                    </span>
                                    <span class="destination-meta">
                                        <span class="destination-time">h {{ formatTime(destination?.time_hiking) }}</span>
                                        <span v-if="destination?.distance" class="destination-distance">
                                            km {{ formatDistance(destination?.distance) }}
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Punta freccia destra per forward -->
                        <div v-if="getArrowDirection(routeId, arrowIdx, arrow.direction) === 'forward'"
                            class="arrow-point-right"></div>
                    </div>
                </div>
            </template>

        </div>

        <!-- Messaggio se non ci sono dati -->
        <div v-if="!hasSignageData" class="no-signage-data">
            Nessun dato segnaletica disponibile
        </div>
    </div>
</template>

<script>
export default {
    name: 'SignageArrowsDisplay',


    props: {
        /**
         * Dati della segnaletica nel formato nuovo:
         * {
         *   "signage": {
         *     "arrow_order": ["6241-0", "6241-1"],
         *     "6241": {
         *       "ref": "100",
         *       "arrows": [
         *         { "direction": "forward", "rows": [...] },
         *         { "direction": "backward", "rows": [...] }
         *       ]
         *     }
         *   }
         * }
         *
         * O formato vecchio (retrocompatibilità):
         * {
         *   "routeId": {
         *     "forward": [...],
         *     "backward": [...]
         *   }
         * }
         */
        signageData: {
            type: Object,
            default: () => ({})
        }
    },

    computed: {
        /**
         * Processa i dati signage mantenendo la struttura originale con arrows ordinate
         * Supporta sia il formato con wrapper "signage" che il formato diretto
         */
        processedSignageData() {
            if (!this.signageData || Object.keys(this.signageData).length === 0) {
                return {};
            }

            // Determina se i dati sono nel formato con wrapper "signage" o diretto
            let signage = this.signageData;
            let arrowOrder = [];
            const effectiveArrowOrder = (this.localArrowOrder && this.localArrowOrder.length > 0)
                ? this.localArrowOrder
                : [];

            // Se ha la struttura con "signage" wrapper
            if (this.signageData.signage) {
                signage = this.signageData.signage;
            }

            // Estrai arrow_order se presente
            if (signage.arrow_order && Array.isArray(signage.arrow_order)) {
                arrowOrder = signage.arrow_order;
                if (effectiveArrowOrder.length === 0) {
                    effectiveArrowOrder.push(...arrowOrder);
                }
            }

            const processed = {};

            // Itera su tutte le chiavi in signage (escludendo arrow_order)
            for (const [key, value] of Object.entries(signage)) {
                if (key === 'arrow_order') {
                    continue;
                }

                const routeId = key;
                const routeData = value;

                // Se ha il formato nuovo con arrows
                if (routeData.arrows && Array.isArray(routeData.arrows)) {
                    // Ordina le arrows secondo arrow_order (o quello locale)
                    const orderedArrows = [];
                    const currentOrder = (effectiveArrowOrder.length > 0 ? effectiveArrowOrder : arrowOrder)
                        .filter(key => key && key.startsWith(`${routeId}-`));

                    if (currentOrder.length > 0) {
                        // Ordina secondo l'ordine calcolato
                        for (const arrowKey of currentOrder) {
                            const [arrowRouteId, arrowIndex] = arrowKey.split('-');
                            if (arrowRouteId === routeId) {
                                const index = parseInt(arrowIndex, 10);
                                const currentArrow = routeData.arrows[index];
                                if (!isNaN(index) && currentArrow &&
                                    currentArrow.rows &&
                                    Array.isArray(currentArrow.rows)) {
                                    orderedArrows.push({
                                        ...currentArrow,
                                        __arrowKey: `${routeId}-${index}`,
                                    });
                                }
                            }
                        }
                    }

                    // Se l'ordine non contiene tutte le frecce, aggiungi le mancanti
                    for (let i = 0; i < routeData.arrows.length; i++) {
                        const arrow = routeData.arrows[i];
                        const key = `${routeId}-${i}`;
                        if (arrow && arrow.rows && Array.isArray(arrow.rows)) {
                            const alreadyAdded = orderedArrows.some(a => a.__arrowKey === key);
                            if (!alreadyAdded) {
                                orderedArrows.push({
                                    ...arrow,
                                    __arrowKey: key,
                                });
                            }
                        }
                    }

                    // Filtra le arrows valide (non undefined/null) e assicurati che orderedArrows sia sempre un array
                    const validArrows = (orderedArrows || []).filter(arrow =>
                        arrow &&
                        typeof arrow === 'object' &&
                        arrow.rows &&
                        Array.isArray(arrow.rows) &&
                        arrow.rows.length > 0
                    );

                    if (validArrows.length > 0) {
                        // Applica le direzioni modificate localmente
                        const arrowsWithLocalDirections = validArrows.map((arrow, idx) => {
                            const key = `${routeId}-${idx}`;
                            const originalKey = arrow.__arrowKey || key;
                            const localDirection = this.localArrowDirections[originalKey];
                            return {
                                ...arrow,
                                __arrowKey: originalKey,
                                direction: localDirection || arrow.direction
                            };
                        });

                        processed[routeId] = {
                            ref: routeData.ref || '',
                            orderedArrows: arrowsWithLocalDirections
                        };
                    }
                }
                // Retrocompatibilità: formato vecchio con forward/backward direttamente
                else if (routeData.forward || routeData.backward) {
                    // Converti il formato vecchio in arrows per compatibilità
                    const orderedArrows = [];
                    if (routeData.forward && Array.isArray(routeData.forward) && routeData.forward.length > 0) {
                        orderedArrows.push({
                            direction: 'forward',
                            rows: routeData.forward
                        });
                    }
                    if (routeData.backward && Array.isArray(routeData.backward) && routeData.backward.length > 0) {
                        orderedArrows.push({
                            direction: 'backward',
                            rows: routeData.backward
                        });
                    }
                    if (orderedArrows.length > 0) {
                        processed[routeId] = {
                            ref: routeData.ref || '',
                            orderedArrows: orderedArrows
                        };
                    }
                }
            }

            return processed;
        },

        hasSignageData() {
            return Object.keys(this.processedSignageData).length > 0;
        }
    },

    data() {
        return {
            localArrowDirections: {}, // Mantiene le direzioni modificate: { "routeId-arrowIdx": "forward|backward" }
            localArrowOrder: [] // Mantiene l'ordine modificato: array di chiavi "routeId-index"
        };
    },

    methods: {
        /**
         * Ottiene la direzione corrente di una freccia (locale o originale)
         * @param {string} routeId - ID della route
         * @param {number} arrowIdx - Indice della freccia nell'array orderedArrows
         * @param {string} originalDirection - Direzione originale dalla freccia
         * @returns {string} - Direzione corrente (forward o backward)
         */
        getArrowDirection(routeId, arrowIdx, originalDirection) {
            const routeData = this.processedSignageData[routeId];
            const key = routeData?.orderedArrows?.[arrowIdx]?.__arrowKey || `${routeId}-${arrowIdx}`;
            return this.localArrowDirections[key] || originalDirection;
        },

        /**
         * Inverte la direzione di una singola freccia
         * @param {string} routeId - ID della route
         * @param {number} arrowIdx - Indice della freccia nell'array orderedArrows
         */
        toggleArrowDirection(routeId, arrowIdx) {
            // #region agent log
            fetch('http://127.0.0.1:7243/ingest/d698a848-ad0a-4be9-8feb-9586ee30a5c3', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ location: 'SignageArrowsDisplay.vue:toggleArrowDirection', message: 'Metodo chiamato', data: { routeId, arrowIdx }, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'G' }) }).catch(() => { });
            // #endregion

            const routeData = this.processedSignageData[routeId];
            if (!routeData || !routeData.orderedArrows || !routeData.orderedArrows[arrowIdx]) {
                // #region agent log
                fetch('http://127.0.0.1:7243/ingest/d698a848-ad0a-4be9-8feb-9586ee30a5c3', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ location: 'SignageArrowsDisplay.vue:toggleArrowDirection', message: 'Route data non valida', data: { routeId, arrowIdx, hasRouteData: !!routeData, hasOrderedArrows: !!(routeData && routeData.orderedArrows) }, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'G' }) }).catch(() => { });
                // #endregion
                return;
            }

            const arrow = routeData.orderedArrows[arrowIdx];
            const key = arrow.__arrowKey || `${routeId}-${arrowIdx}`;
            const currentDirection = this.getArrowDirection(routeId, arrowIdx, arrow.direction);
            const newDirection = currentDirection === 'forward' ? 'backward' : 'forward';

            // #region agent log
            fetch('http://127.0.0.1:7243/ingest/d698a848-ad0a-4be9-8feb-9586ee30a5c3', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ location: 'SignageArrowsDisplay.vue:toggleArrowDirection', message: 'Calcolata nuova direzione', data: { routeId, arrowIdx, currentDirection, newDirection }, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'G' }) }).catch(() => { });
            // #endregion

            // Salva la nuova direzione localmente
            // In Vue 3, $set non è più necessario - l'assegnazione diretta è reattiva
            this.localArrowDirections = {
                ...this.localArrowDirections,
                [key]: newDirection
            };

            // Determina se i dati sono nel formato con wrapper "signage" o diretto
            let signage = this.signageData;
            if (this.signageData.signage) {
                signage = this.signageData.signage;
            }

            // Aggiorna la direzione nell'oggetto arrow originale (per il salvataggio)
            const routeSignage = signage[routeId];
            const originalIndex = parseInt((key.split('-')[1] ?? arrowIdx), 10);
            if (routeSignage && routeSignage.arrows && routeSignage.arrows[originalIndex]) {
                routeSignage.arrows[originalIndex].direction = newDirection;
            }

            // #region agent log
            fetch('http://127.0.0.1:7243/ingest/d698a848-ad0a-4be9-8feb-9586ee30a5c3', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ location: 'SignageArrowsDisplay.vue:toggleArrowDirection', message: 'Prima di emettere evento', data: { routeId, arrowIdx, newDirection }, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'G' }) }).catch(() => { });
            // #endregion

            // Emetti evento per notificare il cambio
            this.$emit('arrow-direction-changed', {
                routeId: routeId,
                arrowIndex: originalIndex,
                newDirection: newDirection,
                fullSignageData: this.signageData
            });

            // #region agent log
            fetch('http://127.0.0.1:7243/ingest/d698a848-ad0a-4be9-8feb-9586ee30a5c3', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ location: 'SignageArrowsDisplay.vue:toggleArrowDirection', message: 'Evento emesso', data: { routeId, arrowIdx, newDirection }, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'G' }) }).catch(() => { });
            // #endregion
        },

        /**
         * Formatta il tempo in ore.minuti
         * @param {number} minutes - minuti totali
         * @returns {string} - tempo formattato (es. "1.15" per 1 ora e 15 minuti)
         */
        formatTime(minutes) {
            if (!minutes && minutes !== 0) return '-';

            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;

            if (hours === 0) {
                return `0.${mins.toString().padStart(2, '0')}`;
            }

            return `${hours}.${mins.toString().padStart(2, '0')}`;
        },

        /**
         * Formatta la distanza in km con una cifra decimale e virgola
         * @param {number} meters - distanza in metri
         * @returns {string} - distanza formattata in km (es. "7,5")
         */
        formatDistance(meters) {
            if (!meters && meters !== 0) return '';

            const km = meters / 1000;
            return km.toFixed(1).replace('.', ',');
        },

        /**
         * Restituisce un titolo pulito per la destinazione.
         * Ordine: name -> ref -> name -> tooltip -> id. Rimuove eventuale prefisso "id".
         */
        formatDestinationName(destination) {
            console.log('destination', destination);
            if (!destination || typeof destination !== 'object') {
                return '-';
            }
            if (destination.name) {
                return destination.name;
            }
            if (destination.ref || destination.tooltip) {
                return 'ref: ' + destination.ref || destination.tooltip;
            }

            return 'id: ' + destination.id;
        },

        /**
         * Restituisce l'ordine corrente (locale o da props)
         */
        getCurrentOrder() {
            if (this.localArrowOrder && this.localArrowOrder.length > 0) {
                return [...this.localArrowOrder];
            }

            let signage = this.signageData;
            if (this.signageData.signage) {
                signage = this.signageData.signage;
            }

            if (signage.arrow_order && Array.isArray(signage.arrow_order)) {
                return [...signage.arrow_order];
            }

            return [];
        },

        /**
         * Verifica se la freccia può essere spostata su
         */
        canMoveUp(routeId, arrowIdx) {
            const routeData = this.processedSignageData[routeId];
            if (!routeData || !routeData.orderedArrows) return false;
            const arrow = routeData.orderedArrows[arrowIdx];
            if (!arrow || !arrow.__arrowKey) return false;

            const order = this.getCurrentOrder().filter(key => key.startsWith(`${routeId}-`));
            const position = order.indexOf(arrow.__arrowKey);
            return position > 0;
        },

        /**
         * Verifica se la freccia può essere spostata giù
         */
        canMoveDown(routeId, arrowIdx) {
            const routeData = this.processedSignageData[routeId];
            if (!routeData || !routeData.orderedArrows) return false;
            const arrow = routeData.orderedArrows[arrowIdx];
            if (!arrow || !arrow.__arrowKey) return false;

            const order = this.getCurrentOrder().filter(key => key.startsWith(`${routeId}-`));
            const position = order.indexOf(arrow.__arrowKey);
            return position !== -1 && position < order.length - 1;
        },

        /**
         * Sposta la freccia in alto o in basso nell'ordine
         */
        moveArrow(routeId, arrowIdx, direction) {
            const routeData = this.processedSignageData[routeId];
            if (!routeData || !routeData.orderedArrows) return;

            const arrow = routeData.orderedArrows[arrowIdx];
            if (!arrow || !arrow.__arrowKey) return;

            const order = this.getCurrentOrder();
            if (order.length === 0) {
                // Se non c'è un ordine, crealo a partire dall'attuale visualizzazione
                const generated = routeData.orderedArrows.map((a) => a.__arrowKey);
                order.push(...generated);
            }

            const routeOrder = order.filter(key => key.startsWith(`${routeId}-`));
            const pos = routeOrder.indexOf(arrow.__arrowKey);
            if (pos === -1) return;

            const target = direction === 'up' ? pos - 1 : pos + 1;
            if (target < 0 || target >= routeOrder.length) return;

            // Scambia le posizioni nell'array di questo routeId
            [routeOrder[pos], routeOrder[target]] = [routeOrder[target], routeOrder[pos]];

            // Ricostruisci l'array completo mantenendo le altre route
            let routePointer = 0;
            const newOrder = order.map(key => {
                if (key.startsWith(`${routeId}-`)) {
                    return routeOrder[routePointer++];
                }
                return key;
            });

            // Se l'array originale non conteneva le frecce (caso generato), aggiungile mantenendo anche le altre
            if (routePointer < routeOrder.length) {
                newOrder.push(...routeOrder.slice(routePointer));
            }

            this.localArrowOrder = newOrder;

            this.$emit('arrow-order-changed', {
                routeId: routeId,
                arrowOrder: newOrder,
                fullSignageData: this.signageData
            });
        }
    }
};
</script>

<style scoped>
.signage-arrows-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 12px 0;
}

.route-signage-block {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.signage-arrow-wrapper {
    display: flex;
    justify-content: flex-start;
    position: relative;
}

/* Stile base freccia */
.signage-arrow {
    display: flex;
    align-items: stretch;
    width: 400px;
    min-width: 400px;
    max-width: 400px;
    height: 150px;
    filter: drop-shadow(1px 2px 3px rgba(0, 0, 0, 0.15));
}

/* Allinea la parte rettangolare: la freccia forward ha un margine sinistro pari alla larghezza della punta */
.signage-arrow.forward {
    margin-left: 30px;
}

/* Corpo della freccia */
.arrow-body {
    display: flex;
    align-items: stretch;
    background: #FFFFFF;
    border: 1px solid #000;
    flex-grow: 1;
    height: 100%;
}

.forward .arrow-body {
    border-right: none;
}

.backward .arrow-body {
    border-left: none;
}

/* Colonna ID Route con barre rosse sopra e sotto */
.route-id-column {
    display: flex;
    flex-direction: column;
    min-width: 55px;
    max-width: 70px;
}

.route-id-red {
    flex: 1;
    background: #C41E3A;
}

.forward .route-id-red {
    border-right: 1px solid #000;
}

.backward .route-id-red {
    border-left: 1px solid #000;
}

.route-id-box {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #FFFFFF;
    border: 1px solid #000;
    padding: 6px 14px;
    font-family: 'Arial', sans-serif;
    font-weight: 700;
    font-size: 20px;
    color: #000000;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}

.forward .route-id-box {
    border-left: none;
}

/* Backward ha il box (e la colonna) a destra */
.backward .route-id-column {
    order: 2;
}

.backward .route-id-box {
    border-right: none;

}

.backward .destinations-list {
    order: 1;
}

/* Lista destinazioni */
.destinations-list {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 4px 0;
    flex-grow: 1;
    min-height: 100%;
}

.destinations-list--single {
    justify-content: center;
}

.destination-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2px 16px;
    gap: 30px;
    position: relative;
    flex: 1;
}

.destination-row--with-separator::after {
    content: '';
    position: absolute;
    left: 16px;
    right: 0;
    bottom: 0;
    border-bottom: 1px solid #000000;
}

.destination-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    flex: 1;
    min-width: 0;
    max-width: 180px;
}

.destination-name {
    font-family: 'Arial', sans-serif;
    font-weight: 600;
    font-size: 15px;
    color: #000000;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.destination-description {
    font-family: 'Arial', sans-serif;
    font-weight: 400;
    font-size: 10px;
    color: #666666;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.destination-distance {
    font-family: 'Arial', sans-serif;
    font-weight: 700;
    font-size: 13px;
    color: #000000;
}

.destination-time {
    font-family: 'Arial', sans-serif;
    font-weight: 700;
    font-size: 13px;
    color: #000000;
    min-width: 55px;
    text-align: right;
}

.destination-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

/* Punta freccia DESTRA (forward) */
.arrow-point-right {
    width: 30px;
    min-width: 30px;
    background: #C41E3A;
    clip-path: polygon(0 0, 0 100%, 100% 50%);
    align-self: stretch;
}

/* Punta freccia SINISTRA (backward) */
.arrow-point-left {
    width: 30px;
    min-width: 30px;
    background: #C41E3A;
    clip-path: polygon(100% 0, 100% 100%, 0 50%);
    align-self: stretch;
}

/* Messaggio nessun dato */
.no-signage-data {
    color: #6B7280;
    font-style: italic;
    padding: 16px;
    text-align: center;
}

/* Controllo direzione per ogni freccia */
.arrow-direction-control {
    position: absolute;
    left: -48px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
}

.arrow-direction-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    padding: 0;
    background: #3490dc;
    color: white;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.arrow-direction-btn:hover {
    background: #2779bd;
    transform: scale(1.1);
}

.arrow-direction-btn:active {
    background: #1c6ca8;
    transform: scale(0.95);
}

.arrow-direction-btn svg {
    width: 14px;
    height: 14px;
}

.signage-arrow-wrapper {
    position: relative;
}

.arrow-order-controls {
    display: flex;
    flex-direction: column;
    gap: 6px;
    position: absolute;
    right: -48px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
}

.arrow-order-btn {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background: #f3f4f6;
    color: #111827;
    cursor: pointer;
    font-size: 12px;
    line-height: 1;
    transition: background 0.15s ease, transform 0.15s ease;
}

.arrow-order-btn:hover:enabled {
    background: #e5e7eb;
    transform: translateY(-1px);
}

.arrow-order-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
