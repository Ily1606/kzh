import { SubmitPluginRequestLicense } from "@/api/generated/model"
import { pluginStoreBodyNameMax, pluginStoreBodySourceLinkMax, pluginStoreBodyTitleMax } from "@/api/generated/zod"
import { toTypedSchema } from "@vee-validate/zod"
import * as z from 'zod'

const licenseValues = Object.values(SubmitPluginRequestLicense) as [string, ...string[]]

const pluginFields = z.object({
  name: z
    .string({ required_error: 'Plugin name is required' })
    .trim()
    .min(1, 'Plugin name is required')
    .max(pluginStoreBodyNameMax, `Plugin name must not exceed ${pluginStoreBodyNameMax} characters`),
  title: z
    .string({ required_error: 'Display title is required' })
    .trim()
    .min(1, 'Display title is required')
    .max(pluginStoreBodyTitleMax, `Display title must not exceed ${pluginStoreBodyTitleMax} characters`),
  license: z.enum(licenseValues, {
    errorMap: () => ({ message: 'Please select a valid license' }),
  }),
  source_link: z
    .string({ required_error: 'Source link is required' })
    .trim()
    .min(1, 'Source link is required')
    .max(pluginStoreBodySourceLinkMax, `Source link must not exceed ${pluginStoreBodySourceLinkMax} characters`)
    .url('Source link must be a valid URL')
    .refine((url) => url.startsWith('https://'), {
      message: 'Source link must start with https://',
    }),
})

export type PluginFields = z.infer<typeof pluginFields>

export const submitPluginSchema = toTypedSchema(pluginFields)

export const updatePluginSchema = toTypedSchema(pluginFields)

/**
 * The defaults both forms start from
 */
export const emptyPluginFields: PluginFields = {
  name: '',
  title: '',
  license: SubmitPluginRequestLicense.MIT,
  source_link: '',
}
