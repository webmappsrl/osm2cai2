<template>
    <PanelItem :index="index" :field="field">
        <template #value>
            <SignageArrowsDisplay :signage-data="signageData" @arrow-direction-changed="handleArrowDirectionChanged"
                @arrow-order-changed="handleArrowOrderChanged" @arrow-midpoint-changed="handleMidpointChange" />
        </template>
    </PanelItem>
</template>

<script>
import SignageArrowsDisplay from './SignageArrowsDisplay.vue';

export default {
    name: 'SignageArrowsDetailField',

    components: {
        SignageArrowsDisplay
    },

    props: ['index', 'resource', 'resourceName', 'resourceId', 'field'],

    data() {
        return {
            localSignageData: null // Dati locali aggiornati
        };
    },

    computed: {
        signageData() {
            // Usa i dati locali se disponibili, altrimenti usa i dati del campo
            return this.localSignageData || this.field.value || {};
        }
    },

    methods: {
        async handleArrowDirectionChanged(event) {
            // Se siamo nella risorsa Poles, usa resourceId come poleId
            if (this.resourceName === 'poles' && this.resourceId) {
                try {
                    const poleId = parseInt(this.resourceId);

                    const response = await Nova.request().patch(
                        `/nova-vendor/signage-map/pole/${poleId}/arrow-direction`,
                        {
                            routeId: event.routeId,
                            arrowIndex: event.arrowIndex,
                            newDirection: event.newDirection
                        }
                    );

                    // Aggiorna i dati locali con la nuova direzione
                    if (response.data?.signageData) {
                        this.localSignageData = response.data.signageData;
                    }

                    Nova.success('Direzione freccia aggiornata con successo');
                } catch (error) {
                    console.error('Errore durante il salvataggio della direzione:', error);
                    Nova.error('Errore durante il salvataggio della direzione');
                }
            } else {
                console.warn('Salvataggio direzione non supportato per questa risorsa:', this.resourceName);
            }
        },

        handleMidpointChange({ routeId, arrowIndex, selectedPoleId }) {
            if (this.resourceName === 'poles' && this.resourceId) {
                const poleId = parseInt(this.resourceId);
                Nova.request()
                    .patch(`/nova-vendor/signage-map/pole/${poleId}/arrow-midpoint`, {
                        hiking_route_id: parseInt(routeId, 10),
                        arrow_index: arrowIndex,
                        selected_pole_id: selectedPoleId,
                    })
                    .then(() => {
                        Nova.success('Meta intermedia aggiornata');
                    })
                    .catch(error => {
                        Nova.error('Errore nel salvataggio');
                        console.error(error);
                    });
            } else {
                console.warn('Salvataggio midpoint non supportato per questa risorsa:', this.resourceName);
            }
        },

        async handleArrowOrderChanged(event) {
            if (this.resourceName === 'poles' && this.resourceId) {
                try {
                    const poleId = parseInt(this.resourceId);

                    const response = await Nova.request().patch(
                        `/nova-vendor/signage-map/pole/${poleId}/arrow-order`,
                        {
                            routeId: event.routeId,
                            arrowOrder: event.arrowOrder
                        }
                    );

                    if (response.data?.signageData) {
                        this.localSignageData = response.data.signageData;
                    }

                    Nova.success('Ordine frecce aggiornato con successo');
                } catch (error) {
                    console.error('Errore durante il salvataggio dell\'ordine frecce:', error);
                    Nova.error('Errore durante il salvataggio dell\'ordine frecce');
                }
            } else {
                console.warn('Salvataggio ordine non supportato per questa risorsa:', this.resourceName);
            }
        }
    }
};
</script>
