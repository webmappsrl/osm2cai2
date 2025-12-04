<template>
    <PanelItem :index="index" :field="field">
        <template #value>
            <!-- Usa FeatureCollectionMap dal wm-package -->
            <FeatureCollectionMap :geojson-url="geojsonUrl" :height="field.height || 500"
                :show-zoom-controls="field.showZoomControls !== false"
                :mouse-wheel-zoom="field.mouseWheelZoom !== false" :drag-pan="field.dragPan !== false"
                :popup-component="'signage-map'" @feature-click="handleFeatureClick" @map-ready="handleMapReady"
                @popup-open="handlePopupOpen" @popup-close="handlePopupClose" />

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
                                <button
                                    type="button"
                                    @click="toggleMeta"
                                    :disabled="isUpdatingMeta"
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                    :class="[metaValue ? 'bg-primary-500' : 'bg-gray-200 dark:bg-gray-600']"
                                    role="switch"
                                    :aria-checked="metaValue">
                                    <span
                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        :style="{ transform: metaValue ? 'translateX(1.25rem)' : 'translateX(0)' }">
                                    </span>
                                </button>
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
// Importa FeatureCollectionMap dal wm-package
import FeatureCollectionMap from '../../../../../wm-package/src/Nova/Fields/FeatureCollectionMap/resources/js/components/FeatureCollectionMap.vue';

export default {
    name: 'SignageMapDetailField',

    components: {
        FeatureCollectionMap
    },

    props: ['index', 'resource', 'resourceName', 'resourceId', 'field'],

    data() {
        return {
            showPopup: false,
            popupTitle: '',
            currentPoleId: null,
            metaValue: false,
            isUpdatingMeta: false,
            cachedProperties: null // Cache delle properties dopo il salvataggio
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

            console.log('SignageMap URL:', baseUrl);

            if (this.field.demEnrichment) {
                return `${baseUrl}?dem_enrichment=1`;
            }

            return baseUrl;
        },

        poleLink() {
            if (!this.currentPoleId) return '#';
            return `/resources/poles/${this.currentPoleId}`;
        }
    },

    mounted() {
        console.log('SignageMap DetailField mounted');
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
        handleFeatureClick(event) {
            console.log('SignageMap: Feature clicked:', event);
        },

        handleMapReady(event) {
            console.log('SignageMap: Map ready:', event);
        },

        handlePopupOpen(event) {
            console.log('SignageMap: Popup open, emitting Nova event:', event);
            // Emetti evento Nova come fa il wm-package
            Nova.$emit('signage-map:popup-open', {
                ...event,
                popupComponent: 'signage-map'
            });
        },

        handlePopupClose(event) {
            console.log('SignageMap: Popup close, emitting Nova event:', event);
            Nova.$emit('signage-map:popup-close', event);
        },

        // Handler per eventi Nova
        async onNovaPopupOpen(event) {
            console.log('SignageMap: Nova popup-open received:', event);
            if (event.popupComponent === 'signage-map') {
                this.currentPoleId = event.id;
                this.popupTitle = event.properties?.name || event.properties?.tooltip || `Palo #${event.id}`;
                this.showPopup = true;
                // Carica il valore di checkpoint per questo palo specifico
                await this.loadMetaValue();
            }
        },

        onNovaPopupClose(event) {
            console.log('SignageMap: Nova popup-close received:', event);
            this.closePopup();
        },

        closePopup() {
            this.showPopup = false;
            this.currentPoleId = null;
            this.popupTitle = '';
            this.metaValue = false;
            // Non resettare cachedProperties, così quando si riapre usa i dati aggiornati
        },

        handleKeydown(event) {
            if (event.key === 'Escape' && this.showPopup) {
                this.closePopup();
            }
        },

        async loadMetaValue() {
            if (!this.currentPoleId) {
                this.metaValue = false;
                return;
            }

            const poleId = parseInt(this.currentPoleId);

            // Usa le properties cached se disponibili, altrimenti quelle del resource
            const properties = this.cachedProperties ||
                (this.resource && this.resource.properties && this.resource.properties.value) ||
                {};

            // Verifica se il palo è presente nell'array checkpoint
            const checkpoint = properties?.signage?.checkpoint || [];
            this.metaValue = checkpoint.some(id => parseInt(id) === poleId || String(id) === String(poleId));

            console.log('Loaded checkpoint value:', { poleId, checkpoint, metaValue: this.metaValue });
        },

        async toggleMeta() {
            if (this.isUpdatingMeta || !this.currentPoleId) {
                console.log('Toggle blocked:', { isUpdatingMeta: this.isUpdatingMeta, currentPoleId: this.currentPoleId });
                return;
            }

            this.isUpdatingMeta = true;
            const oldValue = this.metaValue;
            const newValue = !this.metaValue;
            const poleId = parseInt(this.currentPoleId);

            // Aggiorna immediatamente il valore per feedback visivo
            this.metaValue = newValue;

            try {
                const id = this.resourceId || (this.resource && this.resource.id && this.resource.id.value);

                console.log('Sending request:', { id, poleId, add: newValue });

                // Aggiorna le properties dell'hikingRoute aggiungendo/rimuovendo l'ID del palo dall'array checkpoint
                const response = await Nova.request().patch(
                    `/nova-vendor/signage-map/hiking-route/${id}/properties`,
                    {
                        poleId: poleId,
                        add: newValue
                    }
                );

                console.log('Response received:', response.data);

                // Aggiorna la cache con le properties aggiornate dalla risposta
                if (response.data && response.data.properties) {
                    this.cachedProperties = response.data.properties;
                    console.log('Cached properties updated:', this.cachedProperties);
                }

                console.log('metaValue after response:', this.metaValue);
                Nova.success(`Meta ${newValue ? 'attivato' : 'disattivato'} con successo`);
            } catch (error) {
                console.error('Error updating meta:', error);
                // Ripristina il valore precedente in caso di errore
                this.metaValue = oldValue;
                Nova.error('Errore durante l\'aggiornamento di Meta');
            } finally {
                this.isUpdatingMeta = false;
            }
        }
    }
};
</script>
