<template>
    <div class="signage-arrows-container">
        <!-- Itera su ogni hiking route nel signage -->
        <div v-for="(routeData, routeId) in processedSignageData" :key="routeId" class="route-signage-block">

            <!-- Itera direttamente sulle arrows ordinate -->
            <template v-for="(arrow, arrowIdx) in (routeData.orderedArrows || [])" :key="arrowIdx">
                <div v-if="arrow && arrow.rows && Array.isArray(arrow.rows) && arrow.rows.length > 0"
                    class="signage-arrow-wrapper">
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
                            <div class="route-id-box">{{ routeData?.ref }}</div>
                            <div class="destinations-list">
                                <div v-for="(destination, idx) in arrow.rows" :key="idx" class="destination-row">
                                    <span class="destination-info">
                                        <span class="destination-name">{{ destination?.placeName || destination?.name ||
                                            `Palo #${destination?.id}` }}</span>
                                        <span v-if="destination?.placeDescription" class="destination-description">{{
                                            destination?.placeDescription }}</span>
                                    </span>
                                    <span class="destination-time">h {{ formatTime(destination?.time_hiking) }}</span>
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

            // Se ha la struttura con "signage" wrapper
            if (this.signageData.signage) {
                signage = this.signageData.signage;
            }

            // Estrai arrow_order se presente
            if (signage.arrow_order && Array.isArray(signage.arrow_order)) {
                arrowOrder = signage.arrow_order;
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
                    // Ordina le arrows secondo arrow_order
                    const orderedArrows = [];

                    if (arrowOrder.length > 0) {
                        // Ordina secondo arrow_order
                        for (const arrowKey of arrowOrder) {
                            const [arrowRouteId, arrowIndex] = arrowKey.split('-');
                            if (arrowRouteId === routeId) {
                                const index = parseInt(arrowIndex, 10);
                                if (!isNaN(index) && routeData.arrows[index] &&
                                    routeData.arrows[index].rows &&
                                    Array.isArray(routeData.arrows[index].rows)) {
                                    orderedArrows.push(routeData.arrows[index]);
                                }
                            }
                        }
                    }

                    // Se arrow_order non è disponibile o non contiene tutte le arrows, aggiungi quelle mancanti
                    if (orderedArrows.length < routeData.arrows.length) {
                        for (let i = 0; i < routeData.arrows.length; i++) {
                            const arrow = routeData.arrows[i];
                            // Verifica che l'arrow sia valida prima di aggiungerla
                            if (arrow && arrow.rows && Array.isArray(arrow.rows)) {
                                // Controlla se questa arrow è già stata aggiunta
                                const alreadyAdded = orderedArrows.some(a => a === arrow);
                                if (!alreadyAdded) {
                                    orderedArrows.push(arrow);
                                }
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
                            const localDirection = this.localArrowDirections[key];
                            return {
                                ...arrow,
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
            localArrowDirections: {} // Mantiene le direzioni modificate: { "routeId-arrowIdx": "forward|backward" }
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
            const key = `${routeId}-${arrowIdx}`;
            return this.localArrowDirections[key] || originalDirection;
        },

        /**
         * Inverte la direzione di una singola freccia
         * @param {string} routeId - ID della route
         * @param {number} arrowIdx - Indice della freccia nell'array orderedArrows
         */
        toggleArrowDirection(routeId, arrowIdx) {
            const routeData = this.processedSignageData[routeId];
            if (!routeData || !routeData.orderedArrows || !routeData.orderedArrows[arrowIdx]) {
                return;
            }

            const arrow = routeData.orderedArrows[arrowIdx];
            const key = `${routeId}-${arrowIdx}`;
            const currentDirection = this.getArrowDirection(routeId, arrowIdx, arrow.direction);
            const newDirection = currentDirection === 'forward' ? 'backward' : 'forward';

            // Salva la nuova direzione localmente
            this.$set(this.localArrowDirections, key, newDirection);

            // Determina se i dati sono nel formato con wrapper "signage" o diretto
            let signage = this.signageData;
            if (this.signageData.signage) {
                signage = this.signageData.signage;
            }

            // Aggiorna la direzione nell'oggetto arrow originale (per il salvataggio)
            const routeSignage = signage[routeId];
            if (routeSignage && routeSignage.arrows && routeSignage.arrows[arrowIdx]) {
                routeSignage.arrows[arrowIdx].direction = newDirection;
            }

            // Emetti evento per notificare il cambio
            this.$emit('arrow-direction-changed', {
                routeId: routeId,
                arrowIndex: arrowIdx,
                newDirection: newDirection,
                fullSignageData: this.signageData
            });
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
         * Formatta la distanza in metri
         * @param {number} meters - distanza in metri
         * @returns {string} - distanza formattata
         */
        formatDistance(meters) {
            if (!meters && meters !== 0) return '';

            return `${meters} m`;
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
    min-width: 320px;
    max-width: 400px;
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
    border: 2px solid #C41E3A;
    border-left: none;
    border-right: none;
    flex-grow: 1;
}

/* Barra rossa laterale per forward (sinistra) */
.forward .arrow-body {
    border-left: 10px solid #C41E3A;
}

/* Barra rossa laterale per backward (destra) */
.backward .arrow-body {
    border-right: 10px solid #C41E3A;
}

/* Box ID Route */
.route-id-box {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #FFFFFF;
    border-right: 2px solid #C41E3A;
    padding: 6px 14px;
    min-width: 55px;
    max-width: 70px;
    font-family: 'Arial', sans-serif;
    font-weight: 700;
    font-size: 20px;
    color: #000000;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Backward ha il box a destra */
.backward .route-id-box {
    border-right: none;
    border-left: 2px solid #C41E3A;
    order: 2;
}

.backward .destinations-list {
    order: 1;
}

/* Lista destinazioni */
.destinations-list {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 6px 0;
    flex-grow: 1;
}

.destination-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 16px;
    gap: 30px;
}

.destination-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
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
    font-weight: 400;
    font-size: 12px;
    color: #666666;
}

.destination-time {
    font-family: 'Arial', sans-serif;
    font-weight: 700;
    font-size: 15px;
    color: #000000;
    min-width: 55px;
    text-align: right;
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
    left: -35px;
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
</style>
