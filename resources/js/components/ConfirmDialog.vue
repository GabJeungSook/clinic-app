<script setup lang="ts">
import { ref } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        confirmText?: string;
        cancelText?: string;
    }>(),
    {
        title: 'Are you sure?',
        description: 'This action cannot be undone.',
        confirmText: 'Delete',
        cancelText: 'Cancel',
    },
);

const emit = defineEmits<{ confirm: [] }>();
const open = ref(false);

function confirm() {
    open.value = false;
    emit('confirm');
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-2">
                <DialogClose as-child>
                    <Button variant="ghost">{{ cancelText }}</Button>
                </DialogClose>
                <Button variant="destructive" @click="confirm">{{ confirmText }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
