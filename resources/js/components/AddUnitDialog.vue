<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus } from '@lucide/vue';

// Quick-add a measurement unit without leaving the current form. The unit list
// on the host page refreshes via the redirect (preserveState keeps form state).
const open = ref(false);
const form = useForm({ name: '', abbreviation: '', base_unit_id: '', factor_to_base: 1 });
const save = () =>
    form.post('/inventory/units', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
</script>

<template>
    <Button type="button" variant="ghost" size="sm" @click="open = true"><Plus class="size-4" /> New unit</Button>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader><DialogTitle>New unit</DialogTitle></DialogHeader>
            <form class="grid gap-3" @submit.prevent="save">
                <div class="grid gap-1.5"><Label>Name *</Label><Input v-model="form.name" placeholder="e.g. Ampoule" /></div>
                <div class="grid gap-1.5"><Label>Abbreviation *</Label><Input v-model="form.abbreviation" placeholder="e.g. amp" /></div>
                <DialogFooter class="gap-2 sm:gap-2">
                    <Button type="button" variant="ghost" @click="open = false">Cancel</Button>
                    <Button type="submit" :disabled="form.processing || !form.name || !form.abbreviation">Add unit</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
