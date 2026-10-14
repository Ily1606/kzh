import { SubmitPluginRequestLicense } from "@/api/generated/model"
import {
  pluginStoreBodyNameMax,
  pluginStoreBodySourceLinkMax,
  pluginStoreBodyTagsMax,
  pluginStoreBodyTitleMax,
} from "@/api/generated/zod"
import * as z from 'zod'

const licenseValues = Object.values(SubmitPluginRequestLicense) as [string, ...string[]]

/**
 * Built from the taxonomy the API returns
 */
export function createPluginFieldsSchema(categories: string[], tags: string[]) {
  /**
   * The empty string stands for "nothing chosen yet", so an untouched category is
   * reported as a missing choice rather than as a value the API would reject.
   */
  const categoryChoice = z
    .union([z.literal(''), z.enum(categories as [string, ...string[]])])
    .refine((value) => value !== '', 'Please choose a category')

  return z.object({
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
    category: categoryChoice,
    tags: z
      .array(z.enum(tags as [string, ...string[]], { errorMap: () => ({ message: 'Please select valid tags' }) }))
      .max(pluginStoreBodyTagsMax, `You can pick at most ${pluginStoreBodyTagsMax} tags`),
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
}

export type PluginFields = z.infer<ReturnType<typeof createPluginFieldsSchema>>

/**
 * The defaults both forms start from
 */
export const emptyPluginFields: PluginFields = {
  name: '',
  title: '',
  license: SubmitPluginRequestLicense.MIT,
  category: '',
  tags: [],
  source_link: '',
}
