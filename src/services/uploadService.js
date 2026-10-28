/**
 * services/uploadService.js
 *
 * NOTE: Real upload logic lives in services/postService.js → createPost().
 * This file is kept only for backward-compatible imports from old hooks.
 * It is safe to delete once all consumers have been updated.
 */

export function buildUploadSteps(selectedPlatforms) {
  return ['Uploading to server', ...selectedPlatforms.map((p) => `Publishing to ${p}`), 'Done']
}

export function simulateStep() { return Promise.resolve() }
export function runUploadSimulation() { return Promise.resolve() }
