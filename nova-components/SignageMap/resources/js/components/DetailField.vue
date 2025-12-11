<template>
    <PanelItem :index="index" :field="field">
        <template #value>
            <div style="position: relative;">
                <div style="position: absolute; top: 10px; right: 10px; z-index: 1000;">
                    <button @click="openGeoJSON" type="button" class="btn btn-default btn-primary"
                        style="background-color: #3490dc; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            style="display: inline-block; vertical-align: middle; margin-right: 4px;">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                            <polyline points="15 3 21 3 21 9"></polyline>
                            <line x1="10" y1="14" x2="21" y2="3"></line>
                        </svg>
                        GeoJSON
                    </button>
                </div>
                <!-- Usa FeatureCollectionMap dal wm-package -->
                <FeatureCollectionMap :geojson-url="geojsonUrl" :height="field.height || 500"
                    :show-zoom-controls="field.showZoomControls !== false"
                    :mouse-wheel-zoom="field.mouseWheelZoom !== false" :drag-pan="field.dragPan !== false"
                    :popup-component="'signage-map'" @popup-open="handlePopupOpen" @popup-close="handlePopupClose" />
            </div>

            <!-- Custom Signage Popup -->
            <Teleport to="body">
                <div v-if="showPopup" class="fixed inset-0 z-[9999] flex items-center justify-center p-4" role="dialog"
                    aria-modal="true">
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" @click="closePopup"></div>

                    <!-- Modal -->
                    <div
                        class="relative z-10 bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden w-full max-w-md">
                        <!-- Header -->
                        <div class="bg-primary-500 dark:bg-primary-600 px-6 py-4">
                            <h3 class="text-lg font-semibold text-white">{{ popupTitle }}</h3>
                        </div>

                        <!-- Content -->
                        <div class="px-6 py-4">
                            <p class="text-gray-600 dark:text-gray-300 text-sm">
                                Palo selezionato: <strong>{{ popupTitle }}</strong>
                            </p>
                            <!-- Toggle Meta -->
                            <div class="mt-4 flex items-center justify-between">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Meta
                                </label>
                                <button type="button" @click="toggleMeta" :disabled="isUpdatingMeta"
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                    :class="[metaValue ? 'bg-primary-500' : 'bg-gray-200 dark:bg-gray-600']"
                                    role="switch" :aria-checked="metaValue">
                                    <span
                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        :style="{ transform: metaValue ? 'translateX(1.25rem)' : 'translateX(0)' }">
                                    </span>
                                </button>
                            </div>

                            <!-- Campo Nome Località (visibile solo quando Meta è attivo) -->
                            <div v-if="metaValue" class="mt-4">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">
                                    Nome Località
                                    <span v-if="isLoadingSuggestion" class="text-xs text-gray-400 ml-2">(caricamento suggerimento...)</span>
                                </label>
                                <div class="mb-2 flex gap-2 flex-wrap">
                                    <button
                                        v-if="hasOsmName"
                                        type="button"
                                        @click="recoverOsmName"
                                        class="px-2 py-0.5 text-xs rounded font-medium cursor-pointer transition-colors shadow-sm border"
                                        style="background-color: #3b82f6; color: white; border-color: #2563eb;"
                                    >
                                        Recupera da OSM
                                    </button>
                                    <button
                                        type="button"
                                        @click="suggestPlaceName"
                                        :disabled="isLoadingSuggestion"
                                        class="px-2 py-0.5 text-xs rounded font-medium cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm border"
                                        style="background-color: #3b82f6; color: white; border-color: #2563eb;"
                                        :style="isLoadingSuggestion ? 'opacity: 0.5;' : 'background-color: #3b82f6; color: white; border-color: #2563eb;'"
                                    >
                                        Suggerisci
                                    </button>
                                </div>
                                <input
                                    type="text"
                                    v-model="placeName"
                                    :placeholder="isLoadingSuggestion ? 'Caricamento...' : 'Inserisci il nome della località'"
                                    :disabled="isLoadingSuggestion"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm disabled:opacity-50"
                                />
                            </div>

                            <!-- Campo Descrizione Località (visibile solo quando Meta è attivo) -->
                            <div v-if="metaValue" class="mt-4">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">
                                    Descrizione aggiuntiva
                                </label>
                                <textarea
                                    v-model="placeDescription"
                                    placeholder="Inserisci una descrizione della località"
                                    rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm resize-none"
                                ></textarea>
                            </div>

                            <!-- Bottone Aggiorna -->
                            <div class="mt-4">
                                <button
                                    type="button"
                                    @click="saveChanges"
                                    :disabled="isUpdatingMeta || (metaValue && (!placeName || !placeName.trim()))"
                                    class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded font-medium cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                    {{ isUpdatingMeta ? 'Salvataggio...' : 'Aggiorna' }}
                                </button>
                            </div>

                            <!-- Frecce Segnaletica -->
                            <div class="mt-4 border-t border-gray-200 dark:border-gray-600 pt-4">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 block">
                                    Segnaletica
                                </label>
                                <SignageArrowsDisplay :signage-data="signageArrowsData" />
                            </div>

                            <p class="text-gray-500 dark:text-gray-400 text-xs mt-2">
                                Clicca sul pulsante per visualizzare i dettagli del palo.
                            </p>
                        </div>

                        <!-- Footer with buttons -->
                        <div
                            class="px-6 py-4 bg-gray-100 dark:bg-gray-700 flex justify-end items-center gap-3 border-t border-gray-200 dark:border-gray-600">
                            <button type="button" @click="closePopup"
                                class="bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 px-4 py-2 rounded font-medium cursor-pointer">
                                Chiudi
                            </button>
                            <a :href="poleLink" target="_blank"
                                class="bg-primary-500 hover:bg-primary-600 text-white px-4 py-2 rounded font-medium no-underline cursor-pointer inline-block">
                                Vai al Palo
                            </a>
                        </div>
                    </div>
                </div>
            </Teleport>
        </template>
    </PanelItem>
</template>

<script>
// Importa FeatureCollectionMap dalla copia locale
import FeatureCollectionMap from './FeatureCollectionMap.vue';
// Importa SignageArrowsDisplay per mostrare le frecce segnaletica nel popup
import SignageArrowsDisplay from '../../../../SignageArrows/resources/js/components/SignageArrowsDisplay.vue';

export default {
    name: 'SignageMapDetailField',

    components: {
        FeatureCollectionMap,
        SignageArrowsDisplay
    },

    props: ['index', 'resource', 'resourceName', 'resourceId', 'field'],

    data() {
        return {
            showPopup: false,
            popupTitle: '',
            currentPoleId: null,
            metaValue: false,
            isUpdatingMeta: false,
            cachedProperties: null, // Cache delle properties dopo il salvataggio
            signageArrowsData: {}, // Dati per le frecce segnaletica
            mapKey: 0, // Chiave per forzare il refresh della mappa
            placeName: '', // Nome località del palo
            placeDescription: '', // Descrizione località del palo
            isLoadingSuggestion: false, // Indica se sta caricando il suggerimento
            currentPoleOsmTags: null, // Dati osm_tags del palo corrente
        };
    },

    computed: {
        geojsonUrl() {
            if (this.field.geojsonUrl) {
                return this.field.geojsonUrl;
            }

            const modelName = this.resourceName;
            const id = this.resourceId || (this.resource && this.resource.id && this.resource.id.value);
            const baseUrl = `/nova-vendor/feature-collection-map/${modelName}/${id}`;
            let url = baseUrl;

            if (this.field.demEnrichment) {
                url = `${baseUrl}?dem_enrichment=1`;
            }

            // Aggiungi timestamp solo se mapKey > 0 (dopo un toggle)
            if (this.mapKey > 0) {
                url += `${url.includes('?') ? '&' : '?'}_t=${this.mapKey}`;
            }

            return url;
        },

        poleLink() {
            if (!this.currentPoleId) return '#';
            return `/resources/poles/${this.currentPoleId}`;
        },

        hasOsmName() {
            return !!(this.currentPoleOsmTags && this.currentPoleOsmTags.name);
        },

        osmName() {
            return this.currentPoleOsmTags?.name || '';
        }
    },

    mounted() {
        document.addEventListener('keydown', this.handleKeydown);

        // Ascolta gli eventi Nova come fa il wm-package
        Nova.$on('signage-map:popup-open', this.onNovaPopupOpen);
        Nova.$on('signage-map:popup-close', this.onNovaPopupClose);
    },

    beforeUnmount() {
        document.removeEventListener('keydown', this.handleKeydown);
        Nova.$off('signage-map:popup-open', this.onNovaPopupOpen);
        Nova.$off('signage-map:popup-close', this.onNovaPopupClose);
    },

    methods: {
        openGeoJSON() {
            window.open(this.geojsonUrl, '_blank');
        },
        handlePopupOpen(event) {
            // Emetti evento Nova come fa il wm-package
            Nova.$emit('signage-map:popup-open', {
                ...event,
                popupComponent: 'signage-map'
            });
        },

        handlePopupClose(event) {
            Nova.$emit('signage-map:popup-close', event);
        },

        // Handler per eventi Nova
        async onNovaPopupOpen(event) {
            console.log('SignageMap: Nova popup-open received:', event);
            if (event.popupComponent === 'signage-map') {
                this.currentPoleId = event.id;
                this.popupTitle = event.properties?.name || event.properties?.tooltip || `Palo #${event.id}`;
                // Estrai i dati della segnaletica dalle properties del palo
                this.signageArrowsData = event.properties?.signage || {};
                // Carica il placeName e placeDescription dalle properties del palo se esistono
                this.placeName = event.properties?.placeName || '';
                this.placeDescription = event.properties?.placeDescription || '';
                // Salva i dati osmTags del palo
                this.currentPoleOsmTags = event.properties?.osmTags || null;
                this.showPopup = true;
                // Carica il valore di checkpoint per questo palo specifico
                this.loadMetaValue(event.featuresMap);
            }
        },

        onNovaPopupClose(event) {
            this.closePopup();
        },

        closePopup() {
            this.showPopup = false;
            this.currentPoleId = null;
            this.popupTitle = '';
            this.metaValue = false;
            this.signageArrowsData = {};
            this.placeName = '';
            this.placeDescription = '';
            this.currentPoleOsmTags = null;
        },

        handleKeydown(event) {
            if (event.key === 'Escape' && this.showPopup) {
                this.closePopup();
            }
        },

        loadMetaValue(featuresMap) {
            if (!this.currentPoleId) {
                this.metaValue = false;
                return;
            }

            const poleId = parseInt(this.currentPoleId);

            // Usa le properties cached se disponibili (dopo un toggle)
            if (this.cachedProperties) {
                const checkpoint = this.cachedProperties?.signage?.checkpoint || [];
                this.metaValue = checkpoint.some(id => parseInt(id) === poleId || String(id) === String(poleId));
                return;
            }

            // Cerca nelle featuresMap la feature con signage.checkpoint (è l'HikingRoute)
            if (featuresMap) {
                for (const [featureId, feature] of Object.entries(featuresMap)) {
                    const checkpoint = feature.properties?.signage?.checkpoint;
                    if (checkpoint && Array.isArray(checkpoint)) {
                        this.metaValue = checkpoint.some(id => parseInt(id) === poleId || String(id) === String(poleId));
                        return;
                    }
                }
            }

            this.metaValue = false;
        },

        async toggleMeta() {
            if (this.isUpdatingMeta) {
                return;
            }
            // Cambia solo il valore locale, il salvataggio avviene tramite il bottone Aggiorna
            this.metaValue = !this.metaValue;
            // Se si disattiva Meta, resetta placeName e placeDescription
            if (!this.metaValue) {
                this.placeName = '';
                this.placeDescription = '';
            }
        },

        async suggestPlaceName() {
            if (!this.currentPoleId || this.isLoadingSuggestion) {
                return;
            }

            this.isLoadingSuggestion = true;

            try {
                const response = await Nova.request().get(
                    `/nova-vendor/signage-map/pole/${this.currentPoleId}/suggest-place-name`
                );

                if (response.data?.success && response.data?.suggestedName) {
                    this.placeName = response.data.suggestedName;
                }
            } catch (error) {
                console.warn('Could not fetch place name suggestion:', error);
                // Non mostriamo errore all'utente, il campo rimane vuoto
            } finally {
                this.isLoadingSuggestion = false;
            }
        },

        recoverOsmName() {
            // Recupera il nome da OSM e lo inserisce nel campo placeName
            if (this.hasOsmName) {
                this.placeName = this.osmName;
            }
        },

        async saveChanges() {
            // Se Meta è attivo, richiedi placeName; altrimenti permetti il salvataggio
            if (this.isUpdatingMeta || !this.currentPoleId) {
                return;
            }
            if (this.metaValue && (!this.placeName || !this.placeName.trim())) {
                return;
            }

            this.isUpdatingMeta = true;
            const poleId = parseInt(this.currentPoleId);

            try {
                const id = this.resourceId || (this.resource && this.resource.id && this.resource.id.value);

                // Aggiorna le properties dell'hikingRoute con checkpoint, placeName e placeDescription
                const response = await Nova.request().patch(
                    `/nova-vendor/signage-map/hiking-route/${id}/properties`,
                    {
                        poleId: poleId,
                        add: this.metaValue,
                        placeName: this.metaValue ? this.placeName.trim() : null,
                        placeDescription: this.metaValue ? (this.placeDescription?.trim() || null) : null
                    }
                );

                // Aggiorna la cache con le properties aggiornate dalla risposta
                if (response.data && response.data.properties) {
                    this.cachedProperties = response.data.properties;
                }

                Nova.success(this.metaValue ? 'Dati località salvati con successo' : 'Meta rimossa con successo');

                // Forza il refresh della mappa incrementando la key
                this.mapKey++;
            } catch (error) {
                Nova.error('Errore durante il salvataggio');
            } finally {
                this.isUpdatingMeta = false;
            }
        }
    }
};
</script>
