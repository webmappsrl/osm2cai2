<template>
    <div class="signage-arrows-container">
        <!-- Itera su ogni hiking route nel signage -->
        <div v-for="(routeData, routeId) in signageData" :key="routeId" class="route-signage-block">

            <!-- Frecce Forward (verso destra) -->
            <div v-if="routeData.forward && routeData.forward.length > 0" class="signage-arrow-wrapper">
                <div class="signage-arrow forward">
                    <div class="arrow-body">
                        <div class="route-id-box">{{ routeData?.ref }}</div>
                        <div class="destinations-list">
                            <div v-for="(destination, idx) in routeData.forward" :key="idx" class="destination-row">
                                <span class="destination-info">
                                    <span class="destination-name">{{ destination?.name }}</span>
                                    <span class="destination-distance">{{ formatDistance(destination?.distance) }}</span>
                                </span>
                                <span class="destination-time">h {{ formatTime(destination?.time_hiking) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="arrow-point-right"></div>
                </div>
            </div>

            <!-- Frecce Backward (verso sinistra) -->
            <div v-if="routeData.backward && routeData.backward.length > 0" class="signage-arrow-wrapper">
                <div class="signage-arrow backward">
                    <div class="arrow-point-left"></div>
                    <div class="arrow-body">
                        <div class="route-id-box">{{ routeData?.ref }}</div>
                        <div class="destinations-list">
                            <div v-for="(destination, idx) in routeData.backward" :key="idx" class="destination-row">
                                <span class="destination-info">
                                    <span class="destination-name">{{ destination?.name }}</span>
                                    <span class="destination-distance">{{ formatDistance(destination?.distance) }}</span>
                                </span>
                                <span class="destination-time">h {{ formatTime(destination?.time_hiking) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
         * Dati della segnaletica nel formato:
         * {
         *   "routeId": {
         *     "forward": [{ name, distance, time_hiking }, ...],
         *     "backward": [{ name, distance, time_hiking }, ...]
         *   }
         * }
         */
        signageData: {
            type: Object,
            default: () => ({})
        }
    },

    computed: {
        hasSignageData() {
            return Object.keys(this.signageData).length > 0;
        }
    },

    methods: {
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
    font-family: 'Arial', sans-serif;
    font-weight: 700;
    font-size: 20px;
    color: #000000;
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
    flex-direction: row;
    align-items: center;
    gap: 8px;
}

.destination-name {
    font-family: 'Arial', sans-serif;
    font-weight: 600;
    font-size: 15px;
    color: #000000;
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

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .arrow-body {
        background: #1F2937;
    }

    .route-id-box {
        background: #1F2937;
        color: #FFFFFF;
    }

    .destination-name,
    .destination-time {
        color: #FFFFFF;
    }

    .destination-distance {
        color: #9CA3AF;
    }
}

/* Nova dark theme */
.dark .arrow-body {
    background: #1F2937;
}

.dark .route-id-box {
    background: #1F2937;
    color: #FFFFFF;
}

.dark .destination-name,
.dark .destination-time {
    color: #FFFFFF;
}

.dark .destination-distance {
    color: #9CA3AF;
}
</style>


