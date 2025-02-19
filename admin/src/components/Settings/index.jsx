import { useState, useEffect } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { useWatermark } from "../../hooks/useWatermark";
import PreviewHandler from "./PreviewHandler";

const Settings = () => {
  const {
    watermarkSettings,
    updateSettings,
    saveSettings,
    isLoading,
    error,
    loadSettings,
  } = useWatermark();

  const [localSettings, setLocalSettings] = useState(watermarkSettings);
  const [previewUrl, setPreviewUrl] = useState(null);
  const [originalImageUrl, setOriginalImageUrl] = useState(null);
  const [showOriginal, setShowOriginal] = useState(false);
  const [successMessage, setSuccessMessage] = useState("");

  useEffect(() => {
    loadSettings();
  }, [loadSettings]);

  useEffect(() => {
    setLocalSettings(watermarkSettings);
  }, [watermarkSettings]);

  const handleInputChange = (name, value) => {
    setLocalSettings((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    // Basic validation
    if (localSettings.opacity < 0 || localSettings.opacity > 100) {
      alert("Opacity must be between 0 and 100");
      return;
    }

    if (localSettings.size < 1 || localSettings.size > 100) {
      alert("Size must be between 1 and 100");
      return;
    }

    if (localSettings.rotation < 0 || localSettings.rotation > 360) {
      alert("Rotation must be between 0 and 360 degrees");
      return;
    }

    try {
      // First update local context
      await updateSettings(localSettings);

      // Then save the same settings to backend
      const success = await saveSettings(localSettings);

      if (success) {
        setSuccessMessage("Settings saved successfully");
        setTimeout(() => setSuccessMessage(""), 3000);
      }
    } catch (err) {
      console.error("Error saving settings:", err);
    }
  };

  const openMediaLibrary = () => {
    // Create and open WordPress media uploader
    const mediaUploader = wp.media({
      title: "Select Watermark Image",
      button: {
        text: "Use this image",
      },
      multiple: false,
      library: {
        type: "image",
      },
    });

    // Handle image selection
    mediaUploader.on("select", function () {
      const attachment = mediaUploader
        .state()
        .get("selection")
        .first()
        .toJSON();

      // Create an image element to check dimensions
      const img = new Image();
      img.onload = () => {
        if (img.width > 500 || img.height > 500) {
          alert("Image dimensions should not exceed 500x500 pixels");
          return;
        }

        handleInputChange("watermarkImage", attachment.url);
      };
      img.onerror = () => {
        alert("Failed to load image");
      };
      img.src = attachment.url;
    });

    // Open the uploader
    mediaUploader.open();
  };

  const removeWatermark = () => {
    handleInputChange("watermarkImage", "");
  };

  return (
    <div className="p-6 flex gap-6">
      {/* Left Panel - Settings Form */}
      <div className="w-1/2">
        <form onSubmit={handleSubmit}>
          <Card>
            <CardHeader>
              <CardTitle className="text-xl font-bold">
                Watermark Settings
              </CardTitle>
              <p className="text-sm text-gray-500">
                Configure how your watermark appears on images.
              </p>
            </CardHeader>
            <CardContent>
              <div className="space-y-6">
                {/* Watermark Upload Area */}
                <div className="space-y-4">
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Watermark Image
                  </label>

                  <div className="border rounded-lg p-6 bg-white">
                    {localSettings.watermarkImage ? (
                      <div className="relative">
                        <div className="flex flex-col items-center">
                          <img
                            src={localSettings.watermarkImage}
                            alt="Watermark preview"
                            className="max-w-full h-auto max-h-48 object-contain mb-4"
                          />
                          <div className="flex gap-3">
                            <Button
                              type="button"
                              variant="outline"
                              onClick={openMediaLibrary}
                              className="text-sm"
                            >
                              Change Image
                            </Button>
                            <Button
                              type="button"
                              variant="destructive"
                              onClick={removeWatermark}
                              className="text-sm"
                            >
                              Remove
                            </Button>
                          </div>
                        </div>
                      </div>
                    ) : (
                      <div className="flex flex-col items-center text-center">
                        <div className="mb-4">
                          <Button
                            type="button"
                            onClick={openMediaLibrary}
                            className="w-full min-w-[200px]"
                          >
                            Select from Media Library
                          </Button>
                        </div>
                        <p className="text-sm text-gray-500">
                          Recommended maximum dimensions: 500x500 pixels
                        </p>
                      </div>
                    )}
                  </div>
                </div>

                {/* Position Dropdown */}
                <div className="space-y-2">
                  <label className="block text-sm font-medium">Position</label>
                  <select
                    className="w-full p-2 border rounded-lg bg-white"
                    value={localSettings.position}
                    onChange={(e) =>
                      handleInputChange("position", e.target.value)
                    }
                  >
                    <option value="bottom-right">Bottom Right</option>
                    <option value="bottom-left">Bottom Left</option>
                    <option value="top-right">Top Right</option>
                    <option value="top-left">Top Left</option>
                    <option value="center">Center</option>
                  </select>
                </div>

                {/* Opacity Slider */}
                <div className="space-y-2">
                  <label className="block text-sm font-medium">Opacity</label>
                  <input
                    type="range"
                    min="0"
                    max="100"
                    value={localSettings.opacity}
                    onChange={(e) =>
                      handleInputChange("opacity", parseInt(e.target.value))
                    }
                    className="w-full"
                  />
                  <span>{localSettings.opacity}%</span>
                </div>

                {/* Size Slider */}
                <div className="space-y-2">
                  <label className="block text-sm font-medium">Size</label>
                  <input
                    type="range"
                    min="1"
                    max="100"
                    value={localSettings.size}
                    onChange={(e) =>
                      handleInputChange("size", parseInt(e.target.value))
                    }
                    className="w-full"
                  />
                  <span>{localSettings.size}%</span>
                </div>

                {/* Rotation Slider */}
                <div className="space-y-2">
                  <label className="block text-sm font-medium">Rotation</label>
                  <input
                    type="range"
                    min="0"
                    max="360"
                    value={localSettings.rotation}
                    onChange={(e) =>
                      handleInputChange("rotation", parseInt(e.target.value))
                    }
                    className="w-full"
                  />
                  <span>{localSettings.rotation}°</span>
                </div>

                {/* Toggle Switches */}
                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">
                      Auto-watermark new uploads
                    </span>
                    <Switch
                      checked={localSettings.autoWatermark}
                      onCheckedChange={(checked) =>
                        handleInputChange("autoWatermark", checked)
                      }
                    />
                  </div>

                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">
                      Backup original images
                    </span>
                    <Switch
                      checked={localSettings.backupOriginals}
                      onCheckedChange={(checked) =>
                        handleInputChange("backupOriginals", checked)
                      }
                    />
                  </div>
                </div>

                {/* Form submission */}
                <div className="space-y-4">
                  <button
                    type="submit"
                    className="w-full bg-black text-white py-2 rounded-lg hover:bg-gray-800 transition-colors disabled:bg-gray-400"
                    disabled={isLoading}
                  >
                    {isLoading ? "Saving..." : "Save Settings"}
                  </button>

                  {successMessage && (
                    <Alert className="bg-green-50 border-green-200">
                      <AlertDescription>{successMessage}</AlertDescription>
                    </Alert>
                  )}

                  {error && (
                    <Alert className="bg-red-50 border-red-200">
                      <AlertDescription>{error}</AlertDescription>
                    </Alert>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>
        </form>
      </div>

      {/* Right Panel - Preview */}
      <div className="w-1/2">
        <Card>
          <CardHeader>
            <div className="flex justify-between items-center">
              <CardTitle className="text-xl font-bold">Preview</CardTitle>
              {previewUrl && (
                <div className="flex items-center gap-2">
                  <span className="text-sm">Show Original</span>
                  <Switch
                    checked={showOriginal}
                    onCheckedChange={setShowOriginal}
                  />
                </div>
              )}
            </div>
            <p className="text-sm text-gray-500">
              See how your watermark will appear on images.
            </p>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <PreviewHandler
                onPreviewGenerated={(url) => {
                  setPreviewUrl(url);
                  setShowOriginal(false);
                }}
                watermarkSettings={localSettings}
              />
              {previewUrl && (
                <div className="border rounded-lg h-96 flex items-center justify-center bg-gray-50 overflow-hidden">
                  <img
                    src={showOriginal ? originalImageUrl : previewUrl}
                    alt="Watermark preview"
                    className="max-w-full max-h-full object-contain"
                  />
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
};

export default Settings;
