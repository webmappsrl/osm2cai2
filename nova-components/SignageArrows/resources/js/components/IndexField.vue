<template>
    <span class="text-gray-500 italic text-sm">
        {{ arrowsCount }} frecce
    </span>
</template>

<script>
export default {
    name: 'SignageArrowsIndexField',

    props: ['resourceName', 'resourceId', 'field'],

    computed: {
        arrowsCount() {
            const signage = this.field.value || {};
            let count = 0;
            
            Object.values(signage).forEach(routeData => {
                if (routeData.forward) count += routeData.forward.length;
                if (routeData.backward) count += routeData.backward.length;
            });
            
            return count;
        }
    }
};
</script>


