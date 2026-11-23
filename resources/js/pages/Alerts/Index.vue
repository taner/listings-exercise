<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import Pagination from '../../components/Pagination.vue';
import { formatDate } from '../../format';

defineProps({
    alerts: { type: Object, required: true },
});
</script>

<template>
    <Head title="Alerts" />

    <AppLayout heading="Alerts" subheading="New listings that matched your saved searches.">
        <div v-if="alerts.data.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <section v-for="alert in alerts.data" :key="alert.id" class="flex flex-col gap-2">
                <p class="text-sm text-slate-500">
                    <span class="font-medium text-slate-900">{{ alert.saved_search_label }}</span>
                    &middot; {{ formatDate(alert.created_at) }}
                </p>

                <ListingCard v-if="alert.listing" :listing="alert.listing" />

                <!-- Only live listings are public, so the server sends none. -->
                <p
                    v-else
                    class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500"
                >
                    This listing is no longer available.
                </p>
            </section>
        </div>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No alerts yet. Save a search on the
            <Link href="/" class="font-medium text-slate-900 underline-offset-2 hover:underline">listings</Link>
            page and new matches will appear here.
        </p>

        <Pagination :meta="alerts.meta" :links="alerts.links" />
    </AppLayout>
</template>
