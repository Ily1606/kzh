<script setup lang="ts">
import { useForm } from 'vee-validate'
import type { TypedSchema } from 'vee-validate'
import { SubmitPluginRequestLicense } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { PluginFields } from '@/schemas/plugin.schema'

const props = defineProps<{
  schema: TypedSchema<Partial<PluginFields>, PluginFields>
  initialValues: PluginFields
  submitLabel: string
  /** Resolves on success. A rejection is handled by the page and leaves the form as it is. */
  submit: (values: PluginFields) => Promise<unknown>
}>()

const emit = defineEmits<{
  (e: 'submitted', values: PluginFields): void
}>()

const { defineField, handleSubmit, errors, setErrors, isSubmitting } = useForm({
  validationSchema: props.schema,
  initialValues: props.initialValues,
})

const [name, nameAttrs] = defineField('name')
const [title, titleAttrs] = defineField('title')
const [license, licenseAttrs] = defineField('license')
const [source_link, sourceLinkAttrs] = defineField('source_link')

const onSubmit = handleSubmit(async (values) => {
  try {
    await props.submit(values)
    emit('submitted', values)
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

  // `setErrors` merges rather than replaces, so the fields the server did not
  // reject this time are blanked here. Otherwise a field that was rejected on
  // the previous attempt keeps a message the server has since stopped sending.
  const cleared = { name: '', title: '', license: '', source_link: '' };

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
        <Label for="license" class="text-sm font-medium">
          License <span class="text-primary">*</span>
        </Label>
        <select
          id="license"
          v-model="license"
          v-bind="licenseAttrs"
          class="flex h-11 w-full rounded-xl border border-border/60 bg-muted/40 px-3 py-2 text-sm text-foreground outline-none transition-colors focus-visible:bg-background focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-50"
          :class="{ 'border-destructive focus-visible:ring-destructive': errors.license }"
        >
          <option
            v-for="lic in Object.values(SubmitPluginRequestLicense)"
            :key="lic"
            :value="lic"
          >
            {{ lic }}
          </option>
        </select>
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
