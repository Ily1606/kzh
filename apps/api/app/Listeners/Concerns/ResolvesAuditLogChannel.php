<?php

namespace App\Listeners\Concerns;

/**
 * Resolves the two log channels a queued audit listener writes to.
 *
 * An audit entry and the report of that entry failing must not share a channel.
 * The failure path only runs because the audit channel just failed — a full
 * disk, an unreachable sink, a bad credential — so reporting back into it
 * raises the very exception being reported and the "Auth audit logging failed."
 * line never reaches anyone.
 *
 * Both audit listeners resolve their channels here so the rule cannot drift
 * between them.
 */
trait ResolvesAuditLogChannel
{
    /**
     * Config key holding the channel the audit entries are written to.
     */
    abstract protected function auditChannelConfigKey(): string;

    /**
     * Config key holding the channel the failure report is written to.
     */
    abstract protected function failureChannelConfigKey(): string;

    /**
     * Channel the audit entries go to.
     *
     * `??` rather than a config() default: the audit key always exists and is
     * simply null when its env variable is unset, and a default passed as the
     * second argument only applies to a missing key, never to a null value.
     */
    protected function auditChannel(): ?string
    {
        return config($this->auditChannelConfigKey()) ?? config('logging.default');
    }

    /**
     * Channel the terminal failure is reported on, kept off the audit channel.
     *
     * stderr does not touch the log file, which is what makes it a safe rescue
     * when the disk is full. An operator pointing the failure channel at the
     * audit channel by hand is stepped over, since that would defeat the point.
     */
    protected function failureChannel(): string
    {
        $auditChannel = $this->auditChannel();
        $fallback = config($this->failureChannelConfigKey(), 'stderr');

        return $fallback === $auditChannel ? 'stderr' : $fallback;
    }
}
