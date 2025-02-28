export const validateWatermarkSettings = (settings) => {
  const errors = {}

  if (!settings.watermarkImage) {
    errors.watermarkImage = "Watermark image is required"
  }

  if (settings.opacity !== undefined && (settings.opacity < 0 || settings.opacity > 100)) {
    errors.opacity = "Opacity must be between 0 and 100"
  }

  if (settings.size !== undefined && (settings.size < 1 || settings.size > 100)) {
    errors.size = "Size must be between 1 and 100"
  }

  if (settings.rotation !== undefined && (settings.rotation < 0 || settings.rotation > 360)) {
    errors.rotation = "Rotation must be between 0 and 360 degrees"
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors
  }
}