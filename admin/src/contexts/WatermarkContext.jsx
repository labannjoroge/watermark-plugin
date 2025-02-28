// WatermarkContext.jsx
import { createContext, useState, useCallback } from "react";

const WatermarkContext = createContext(null);

export const WatermarkProvider = ({ children }) => {
  const [watermarkSettings, setWatermarkSettings] = useState({
    watermarkImage: "",
    position: "bottom-right",
    opacity: 50,
    size: 50,
    rotation: 0,
    autoWatermark: false,
    backupOriginals: true,
  });

  const [previewImage, setPreviewImage] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);
  const [originalImage, setOriginalImage] = useState(null);
  const [isBulkProcessing, setIsBulkProcessing] = useState(false);

  const resetSettings = useCallback(() => {
    setWatermarkSettings({
      watermarkImage: "",
      position: "bottom-right",
      opacity: 50,
      size: 50,
      rotation: 0,
      autoWatermark: false,
      backupOriginals: true,
    });
  }, []);

  const generatePreview = useCallback(
    async (settings, imageData = null, mediaId = null) => {
      setIsLoading(true);
      setError(null);

      try {
        const requestBody = {
          settings: settings,
        };

        if (imageData) {
          requestBody.imageData = imageData;
        }

        if (mediaId) {
          requestBody.mediaId = mediaId;
        }

        const response = await fetch(`${window.WMData.restUrl}preview`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": window.WMData.nonce,
          },
          body: JSON.stringify(requestBody),
        });

        if (!response.ok) {
          const errorData = await response.json();
          throw new Error(errorData.message || "Preview generation failed");
        }

        const data = await response.json();
        setPreviewImage(data.previewUrl);
        return data.previewUrl;
      } catch (err) {
        setError(err.message);
        throw err;
      } finally {
        setIsLoading(false);
      }
    },
    []
  );


  const loadSettings = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(`${window.WMData.restUrl}settings`, {
        headers: {
          "X-WP-Nonce": window.WMData.nonce,
        },
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || "Failed to load settings");
      }

      const data = await response.json();

      setWatermarkSettings(data);
    } catch (err) {
      setError(err.message);
      console.error("Settings fetch error:", err);
    } finally {
      setIsLoading(false);
    }
  }, []);

  const updateSettings = useCallback((newSettings) => {
    return new Promise((resolve) => {
      setWatermarkSettings(newSettings);
      resolve(newSettings);
    });
  }, []);

  const saveSettings = useCallback(async (settingsToSave) => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(`${window.WMData.restUrl}settings`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": window.WMData.nonce,
        },
        body: JSON.stringify(settingsToSave),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || "Failed to save settings");
      }

      // Validate returned settings
      if (!data.settings || typeof data.settings !== "object") {
        throw new Error("Invalid settings response format");
      }

      // Update state with validated settings
      setWatermarkSettings(data.settings);

      // Return success status and settings
      return {
        success: true,
        settings: data.settings,
      };
    } catch (err) {
      const errorMessage = err.message || "An unexpected error occurred";
      setError(errorMessage);
      console.error("Settings save error:", err);

      return {
        success: false,
        error: errorMessage,
      };
    } finally {
      setIsLoading(false);
    }
  }, []);

  const value = {
    watermarkSettings,
    updateSettings,
    resetSettings,
    previewImage,
    generatePreview,
    saveSettings,
    loadSettings,
    isLoading,
    error,
    originalImage,               
    setOriginalImage,
    isBulkProcessing, 
    setIsBulkProcessing,
  };

  return (
    <WatermarkContext.Provider value={value}>
      {children}
    </WatermarkContext.Provider>
  );
};

export default WatermarkContext;
