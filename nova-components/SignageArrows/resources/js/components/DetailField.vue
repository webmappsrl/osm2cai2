<template>
    <PanelItem :index="index" :field="field">
        <template #value>
            <SignageArrowsDisplay :signage-data="signageData" @arrow-direction-changed="handleArrowDirectionChanged"
                @arrow-order-changed="handleArrowOrderChanged" />
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
            // #region agent log
            fetch('http://127.0.0.1:7243/ingest/d698a848-ad0a-4be9-8feb-9586ee30a5c3', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ location: 'SignageArrowsDetailField.vue:handleArrowDirectionChanged', message: 'Evento arrow-direction-changed ricevuto', data: { routeId: event.routeId, arrowIndex: event.arrowIndex, newDirection: event.newDirection, resourceId: this.resourceId, resourceName: this.resourceName }, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'F' }) }).catch(() => { });
            // #endregion

            // Se siamo nella risorsa Poles, usa resourceId come poleId
            if (this.resourceName === 'poles' && this.resourceId) {
                try {
                    const poleId = parseInt(this.resourceId);
                    // #region agent log
                    fetch('http://127.0.0.1:7243/ingest/d698a848-ad0a-4be9-8feb-9586ee30a5c3', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ location: 'SignageArrowsDetailField.vue:handleArrowDirectionChanged', message: 'Chiamata API per salvare direzione (Pole)', data: { poleId, routeId: event.routeId, arrowIndex: event.arrowIndex, newDirection: event.newDirection }, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'F' }) }).catch(() => { });
                    // #endregion

                    const response = await Nova.request().patch(
                        `/nova-vendor/signage-map/pole/${poleId}/arrow-direction`,
                        {
                            routeId: event.routeId,
                            arrowIndex: event.arrowIndex,
                            newDirection: event.newDirection
                        }
                    );

                    // #region agent log
                    fetch('http://127.0.0.1:7243/ingest/d698a848-ad0a-4be9-8feb-9586ee30a5c3', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ location: 'SignageArrowsDetailField.vue:handleArrowDirectionChanged', message: 'Risposta API ricevuta (Pole)', data: { success: response.data?.success, status: response.status }, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'F' }) }).catch(() => { });
                    // #endregion

                    // Aggiorna i dati locali con la nuova direzione
                    if (response.data?.signageData) {
                        this.localSignageData = response.data.signageData;
                        // #region agent log
                        fetch('http://127.0.0.1:7243/ingest/d698a848-ad0a-4be9-8feb-9586ee30a5c3', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ location: 'SignageArrowsDetailField.vue:handleArrowDirectionChanged', message: 'localSignageData aggiornato', data: { hasData: !!this.localSignageData }, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'F' }) }).catch(() => { });
                        // #endregion
                    }

                    Nova.success('Direzione freccia aggiornata con successo');
                } catch (error) {
                    // #region agent log
                    fetch('http://127.0.0.1:7243/ingest/d698a848-ad0a-4be9-8feb-9586ee30a5c3', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ location: 'SignageArrowsDetailField.vue:handleArrowDirectionChanged', message: 'Errore durante salvataggio (Pole)', data: { error: error.message, status: error.response?.status }, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'F' }) }).catch(() => { });
                    // #endregion
                    console.error('Errore durante il salvataggio della direzione:', error);
                    Nova.error('Errore durante il salvataggio della direzione');
                }
            } else {
                // #region agent log
                fetch('http://127.0.0.1:7243/ingest/d698a848-ad0a-4be9-8feb-9586ee30a5c3', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ location: 'SignageArrowsDetailField.vue:handleArrowDirectionChanged', message: 'Resource non supportato per salvataggio', data: { resourceName: this.resourceName, resourceId: this.resourceId }, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'F' }) }).catch(() => { });
                // #endregion
                console.warn('Salvataggio direzione non supportato per questa risorsa:', this.resourceName);
            }
        },

        async handleArrowOrderChanged(event) {
            // #region agent log
            fetch('http://127.0.0.1:7243/ingest/d698a848-ad0a-4be9-8feb-9586ee30a5c3', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ location: 'SignageArrowsDetailField.vue:handleArrowOrderChanged', message: 'Evento arrow-order-changed ricevuto', data: { routeId: event.routeId, resourceId: this.resourceId, resourceName: this.resourceName }, timestamp: Date.now(), sessionId: 'debug-session', runId: 'run1', hypothesisId: 'F' }) }).catch(() => { });
            // #endregion

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
