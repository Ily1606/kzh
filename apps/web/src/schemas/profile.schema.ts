import { toTypedSchema } from '@vee-validate/zod';
import {
  ProfileUpdateProfileBody,
  ProfileUpdatePasswordBody,
  profileUpdateProfileBodyNameMax,
  profileUpdateProfileBodyGithubNameMax,
  profileUpdateProfileBodyGithubLinkMax,
  profileUpdatePasswordBodyNewPasswordMin,
} from '@/api/generated/zod/profile/profile';
import * as z from 'zod';

export const profileSchema = toTypedSchema(
  ProfileUpdateProfileBody.extend({
    name: z.string()
      .min(1, 'Name is required')
      .max(profileUpdateProfileBodyNameMax, `Name must be at most ${profileUpdateProfileBodyNameMax} characters`),

    githubName: z.string()
      .max(profileUpdateProfileBodyGithubNameMax, `GitHub username must be at most ${profileUpdateProfileBodyGithubNameMax} characters`)
      .nullish(),

    // Ô text luôn trả về '' khi để trống, API kỳ vọng null nên phải cho qua ''
    githubLink: z.string()
      .url('Must be a valid URL')
      .max(profileUpdateProfileBodyGithubLinkMax, `GitHub URL must be at most ${profileUpdateProfileBodyGithubLinkMax} characters`)
      .or(z.literal(''))
      .optional()
      .nullable(),
  })
);

export const passwordSchema = toTypedSchema(
  ProfileUpdatePasswordBody.extend({
    current_password: z.string().min(1, 'Current password is required'),

    new_password: z.string().min(
      profileUpdatePasswordBodyNewPasswordMin,
      `Password must be at least ${profileUpdatePasswordBodyNewPasswordMin} characters`,
    ),

    new_password_confirmation: z.string().min(1, 'Please confirm your password'),
  }).refine(data => data.new_password === data.new_password_confirmation, {
    message: 'Passwords do not match',
    path: ['new_password_confirmation'],
  })
);
