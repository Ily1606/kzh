<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useForm } from 'vee-validate'
import { SubmitPluginRequestLicense } from '@/api/generated/model'
import { toTypedSchema } from '@vee-validate/zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import TagPicker from '@/components/pages/plugin/TagPicker.vue'
import { useTaxonomyStore } from '@/stores'
import type { PluginFields } from '@/schemas/plugin.schema'
import { createPluginFieldsSchema } from '@/schemas/plugin.schema'

const props = defineProps<{
  initialValues: PluginFields
  submitLabel: string
  /** Resolves on success. A rejection is handled by the page and leaves the form as it is. */
  submit: (values: PluginFields) => Promise<unknown>
}>()

const licenses = Object.values(SubmitPluginRequestLicense)

// The category and tag lists come from the API, so the schema that checks the
// choices cannot be built until they arrive.
const taxonomy = useTaxonomyStore()
onMounted(() => taxonomy.ensureLoaded())

const categories = computed(() => taxonomy.categories)
const schema = computed(() => toTypedSchema(createPluginFieldsSchema(taxonomy.categories, taxonomy.tags)))

const { defineField, handleSubmit, errors, setErrors, isSubmitting } = useForm({
  validationSchema: schema,
  initialValues: props.initialValues,
})

const [name, nameAttrs] = defineField('name')
const [title, titleAttrs] = defineField('title')
const [license, licenseAttrs] = defineField('license')
const [category, categoryAttrs] = defineField('category')
const [tags, tagsAttrs] = defineField('tags')

const [source_link, sourceLinkAttrs] = defineField('source_link')

const onSubmit = handleSubmit(async (values) => {
  try {
    await props.submit(values)
  } catch {
    // The page owns the failure: it showed the message, and the form stays
    // exactly as the author left it.
  }
})

/**
 * Write server-side failures onto the fields they belong to.
 */
function showServerErrors(fieldErrors: Record<string, string[]>): void {
  const messages = Object.entries(fieldErrors).reduce<Record<string, string>>((acc, [field, list]) => {
    if (list?.[0]) acc[field] = list[0]

    return acc;
  }, {});

  const cleared = { name: '', title: '', license: '', category: '', tags: '', source_link: '' };
  setErrors({ ...cleared, ...messages });
}

defineExpose({ showServerErrors })
</script>

<template>
  <form novalidate class="space-y-6" @submit="onSubmit">
    <!-- Plugin Name -->
    <div class="space-y-2">
      <Label for="name" class="text-sm font-medium">
        Plugin name <span class="text-primary">*</span>
      </Label>
      <Input
        id="name"
        v-model="name"
        v-bind="nameAttrs"
        placeholder="e.g. @dsh/guardrails"
        class="h-11 rounded-xl border-border/60 bg-muted/40 transition-colors focus-visible:bg-background"
        :class="{ 'border-destructive focus-visible:ring-destructive': errors.name }"
      />
      <p v-if="errors.name" class="text-xs text-destructive">{{ errors.name }}</p>
      <p v-else class="text-xs text-muted-foreground">The unique npm or scoped package identifier.</p>
    </div>

    <!-- Display Title -->
    <div class="space-y-2">
      <Label for="title" class="text-sm font-medium">
        Display title <span class="text-primary">*</span>
      </Label>
      <Input
        id="title"
        v-model="title"
        v-bind="titleAttrs"
        placeholder="e.g. Opinionated safety checks and policy hooks"
        class="h-11 rounded-xl border-border/60 bg-muted/40 transition-colors focus-visible:bg-background"
        :class="{ 'border-destructive focus-visible:ring-destructive': errors.title }"
      />
      <p v-if="errors.title" class="text-xs text-destructive">{{ errors.title }}</p>
    </div>

    <!-- License & Source Link Grid -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
      <!-- License -->
      <div class="space-y-2">
        <Label class="text-sm font-medium">
          License <span class="text-primary">*</span>
        </Label>
        <Select v-model="license" v-bind="licenseAttrs">
          <SelectTrigger
            id="license"
            class="w-full rounded-xl border-border/60 bg-muted/40 transition-colors focus-visible:bg-background data-[size=default]:h-11"
            :class="{ 'border-destructive focus-visible:ring-destructive': errors.license }"
          >
            <SelectValue placeholder="Select a license..." />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="license in licenses"
              :key="license"
              :value="license"
            >
              {{ license }}
            </SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.license" class="text-xs text-destructive">{{ errors.license }}</p>
      </div>

      <!-- Source Link -->
      <div class="space-y-2">
        <Label for="source" class="text-sm font-medium">
          Source link <span class="text-primary">*</span>
        </Label>
        <Input
          id="source"
          type="url"
          v-model="source_link"
          v-bind="sourceLinkAttrs"
          placeholder="https://github.com/org/repo"
          class="h-11 rounded-xl border-border/60 bg-muted/40 transition-colors focus-visible:bg-background"
          :class="{ 'border-destructive focus-visible:ring-destructive': errors.source_link }"
        />
        <p v-if="errors.source_link" class="text-xs text-destructive">{{ errors.source_link }}</p>
      </div>

      <!-- Category -->
      <div class="space-y-2">
        <Label class="text-sm font-medium">
          Category <span class="text-primary">*</span>
        </Label>
        <Select v-model="category" v-bind="categoryAttrs">
          <SelectTrigger
            id="category"
            :class="[
              'w-full rounded-xl border-border/60 bg-muted/40 transition-colors focus-visible:bg-background data-[size=default]:h-11',
              errors.category ? 'border-destructive focus-visible:ring-destructive' : '',
            ]"
          >
            <SelectValue placeholder="Select a category..." />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="category in categories"
              :key="category"
              :value="category"
            >
              {{ category }}
            </SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.category" class="text-xs text-destructive">{{ errors.category }}</p>
        <p v-else class="text-xs text-muted-foreground">The single area this plugin belongs to.</p>
      </div>

      <!-- Tags -->
      <div class="space-y-2">
        <Label class="text-sm font-medium">
          Tags
        </Label>
        <TagPicker v-model="tags" v-bind="tagsAttrs" :tags="taxonomy.tags" :error="errors.tags" />
        <p v-if="errors.tags" class="text-xs text-destructive">{{ errors.tags }}</p>
        <p v-else class="text-xs text-muted-foreground">What your plugin is allowed to do</p>
      </div>
    </div>

    <!-- Submit -->
    <div class="pt-2">
      <Button
        type="submit"
        :disabled="isSubmitting"
        class="h-12 cursor-pointer rounded-full px-8 text-sm font-semibold shadow-lg shadow-primary/20 hover:shadow-primary/30"
      >
        {{ isSubmitting ? 'Saving...' : submitLabel }}
      </Button>
    </div>
  </form>
</template>
