import React, { useState } from 'react';
import { useWatermark } from '../../hooks/useWatermark';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Upload, Image as ImageIcon } from 'lucide-react';

const PreviewAndBulkSection = ({ watermarkSettings }) => {
  const [previewUrl, setPreviewUrl] = useState(null);
  const [originalImageUrl, setOriginalImageUrl] = useState(null);
  const [showOriginal, setShowOriginal] = useState(false);
  const [selectedImage, setSelectedImage] = useState(null);
  const [error, setError] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isBulkProcessing, setIsBulkProcessing] = useState(false);
  const [bulkSuccess, setBulkSuccess] = useState(false);

  const { generatePreview, watermarkAllImages } = useWatermark();

  const handleImageSelection = async (image) => {
    setIsLoading(true);
    setError(null);
    try {
      setSelectedImage(image);
      setOriginalImageUrl(image.full);
      const previewUrl = await generatePreview(watermarkSettings, null, image.id);
      setPreviewUrl(previewUrl);
    } catch (err) {
      setError(err.message);
    } finally {
      setIsLoading(false);
    }
  };

  const openMediaLibrary = () => {
    const mediaUploader = wp.media({
      title: 'Select Image for Preview',
      button: { text: 'Use this image' },
      multiple: false,
      library: { type: 'image' }
    });

    mediaUploader.on('select', () => {
      const attachment = mediaUploader.state().get('selection').first().toJSON();
      handleImageSelection({
        id: attachment.id,
        full: attachment.url,
        title: attachment.title
      });
    });

    mediaUploader.open();
  };

  const handleBulkWatermark = async () => {
    setIsBulkProcessing(true);
    setError(null);
    setBulkSuccess(false);

    try {
      const result = await watermarkAllImages();
      
      if (result.success) {
        setBulkSuccess(true);
        // Clear success message after 3 seconds
        setTimeout(() => setBulkSuccess(false), 3000);
      } else {
        throw new Error(result.message || 'Bulk watermark failed');
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setIsBulkProcessing(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* Preview Section */}
      <Card>
        <CardHeader>
          <CardTitle>Preview</CardTitle>
          <p className="text-sm text-gray-500">
            Preview how your watermark will appear
          </p>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="bg-gray-100 rounded-lg h-[400px] flex items-center justify-center relative overflow-hidden">
              {isLoading ? (
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900" />
              ) : previewUrl ? (
                <img
                  src={showOriginal ? originalImageUrl : previewUrl}
                  alt="Preview"
                  className="max-w-full max-h-full object-contain"
                />
              ) : (
                <div className="flex flex-col items-center text-gray-400">
                  <ImageIcon size={48} className="mb-2" />
                  <p>Select an image to preview</p>
                </div>
              )}
            </div>
            
            <div className="flex justify-between items-center">
              <div className="flex items-center gap-2">
                <span className="text-sm text-gray-600">Show Original</span>
                <Switch
                  checked={showOriginal}
                  onCheckedChange={setShowOriginal}
                  disabled={!previewUrl}
                />
              </div>
              <Button 
                variant="outline" 
                onClick={openMediaLibrary}
                className="flex items-center gap-2"
              >
                <Upload size={16} />
                Select Image
              </Button>
            </div>

            {error && (
              <Alert variant="destructive">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Bulk Actions Section */}
      <Card>
        <CardHeader>
          <CardTitle>Bulk Actions</CardTitle>
          <p className="text-sm text-gray-500">
            Apply watermark to multiple images at once
          </p>
        </CardHeader>
        <CardContent className="space-y-4">
          <Button 
            className="w-full bg-black text-white disabled:bg-gray-400" 
            onClick={handleBulkWatermark}
            disabled={isBulkProcessing}
          >
            {isBulkProcessing ? 'Processing...' : 'Start Bulk Watermark'}
          </Button>

          {bulkSuccess && (
            <Alert className="bg-green-50 border-green-200">
              <AlertDescription>
                All images have been successfully watermarked
              </AlertDescription>
            </Alert>
          )}

          {error && (
            <Alert variant="destructive">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default PreviewAndBulkSection;