<template>
    <PanelItem :index="index" :field="field">
        <template #value>
            <SignageArrowsDisplay :signage-data="signageData" />
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

    computed: {
        signageData() {
            return this.field.value || {};
        }
    }
};
</script>
