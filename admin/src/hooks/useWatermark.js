import { useContext, useCallback } from "react";
import WatermarkContext from "../contexts/WatermarkContext";

export const useWatermark = () => {
  const context = useContext(WatermarkContext);

  if (!context) {
    throw new Error("useWatermark must be used within a WatermarkProvider");
  }

  const {
    watermarkSettings,
    updateSettings,
    resetSettings,
    previewImage,
    generatePreview,
    saveSettings,
    loadSettings,
    isLoading,
    error,
  } = context;


  
  const bulkWatermark = useCallback(async (imageIds) => {
    try {
      const response = await fetch(`${window.WMData.restUrl}bulk-watermark`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": window.WMData.nonce,
        },
        body: JSON.stringify({ 
          imageIds, 
          watermarkOptions: watermarkSettings 
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || "Bulk watermark failed");
      }

      return await response.json();
    } catch (err) {
      throw new Error(err.message);
    }
  }, [watermarkSettings]);

  const restoreOriginal = useCallback(async (imageId) => {
    try {
      const response = await fetch(
        `${window.WMData.restUrl}restore/${imageId}`,
        {
          method: "POST",
          headers: {
            "X-WP-Nonce": window.WMData.nonce,
          },
        }
      );
  
      if (!response.ok) {
        const errorData = await response.json();
        console.error('Restore failed:', errorData);
        throw new Error(
          errorData.message || `Failed to restore original image (Status: ${response.status})`
        );
      }
  
      return await response.json();
    } catch (err) {
      console.error('Restore error:', err);
      throw new Error(err.message);
    }
  }, []);

  const getWatermarkedImages = useCallback(async () => {
    try {
      const response = await fetch(
        `${window.WMData.restUrl}watermarked-images`,
        {
          headers: {
            "X-WP-Nonce": window.WMData.nonce,
          },
        }
      );

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(
          errorData.message || "Failed to fetch watermarked images"
        );
      }

      return await response.json();
    } catch (err) {
      throw new Error(err.message);
    }
  }, []);
  const generatePreviewWithImage = useCallback(
    async (settings, file) => {
      if (file instanceof Blob) {
        return new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onloadend = async () => {
            try {
              const previewUrl = await generatePreview(settings, reader.result);
              resolve(previewUrl);
            } catch (err) {
              reject(err);
            }
          };
          reader.onerror = () => reject(new Error("Failed to read file"));
          reader.readAsDataURL(file);
        });
      } else if (typeof file === "number") {
        // Handle WordPress media library attachment ID
        try {
          const response = await fetch(
            `${window.WMData.restUrl}preview/${file}`,
            {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": window.WMData.nonce,
              },
              body: JSON.stringify({ settings }),
            }
          );

          if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || "Failed to generate preview");
          }

          const data = await response.json();
          return data.previewUrl;
        } catch (err) {
          throw new Error(`Failed to generate preview: ${err.message}`);
        }
      } else {
        return {
          previewUrl: data.previewUrl,
          originalUrl: originalImageUrl,
        };
      }
    },
    [generatePreview]
  );

  const applyWatermark = useCallback(async (imageId, settings) => {
    try {
      const response = await fetch(`${window.WMData.restUrl}apply/${imageId}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": window.WMData.nonce,
        },
        body: JSON.stringify({ settings }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || "Failed to apply watermark");
      }

      return await response.json();
    } catch (err) {
      throw new Error(err.message);
    }
  }, []);

  const watermarkAllImages = useCallback(async () => {
    try {
      const response = await fetch(`${window.WMData.restUrl}watermark-all`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": window.WMData.nonce,
        },
        body: JSON.stringify({ 
          watermarkOptions: watermarkSettings 
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || "Watermark all images failed");
      }

      return await response.json();
    } catch (err) {
      throw new Error(err.message);
    }
  }, [watermarkSettings]);

  const getNonWatermarkedImages = useCallback(async () => {
    try {
      const response = await fetch(
        `${window.WMData.restUrl}non-watermarked-images`,
        {
          headers: {
            "X-WP-Nonce": window.WMData.nonce,
          },
        }
      );

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(
          errorData.message || "Failed to fetch non-watermarked images"
        );
      }

      return await response.json();
    } catch (err) {
      throw new Error(err.message);
    }
  }, []);

  return {
    watermarkSettings,
    updateSettings,
    resetSettings,
    previewImage,
    generatePreview,
    generatePreviewWithImage,
    saveSettings,
    loadSettings,
    bulkWatermark,
    restoreOriginal,
    applyWatermark,
    isLoading,
    error,
    watermarkAllImages,
    getNonWatermarkedImages,
    getWatermarkedImages,
  };
};

export default useWatermark;
