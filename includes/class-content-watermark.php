<?php

namespace WatermarkManager\Includes;

/**
 * Handles content watermarking functionality.
 *
 * @since      1.0.0
 * @package    WatermarkManager
 * @subpackage WatermarkManager/includes
 */
class ContentWatermark {

    /**
     * Apply watermark to content.
     *
     * @param string $content The content to watermark.
     * @param array  $options Watermark options.
     * @return string The watermarked content.
     */
    public function apply_watermark(string $content, array $options): string {
        if ($options['type'] === 'text') {
            return $this->apply_text_watermark($content, $options);
        } elseif ($options['type'] === 'image') {
            return $this->apply_image_watermark($content, $options);
        }

        return $content;
    }

    /**
     * Apply text watermark to content.
     *
     * @param string $content The content to watermark.
     * @param array  $options Watermark options.
     * @return string The watermarked content.
     */
    private function apply_text_watermark(string $content, array $options): string {
        $watermark_html = sprintf(
            '<div class="WM-text-watermark" style="%s">%s</div>',
            esc_attr($this->get_watermark_styles($options)),
            esc_html($options['text'])
        );

        return $content . $watermark_html;
    }

    /**
     * Apply image watermark to content.
     *
     * @param string $content The content to watermark.
     * @param array  $options Watermark options.
     * @return string The watermarked content.
     */
    private function apply_image_watermark(string $content, array $options): string {
        $watermark_html = sprintf(
            '<div class="WM-image-watermark" style="%s"><img src="%s" alt="Watermark"></div>',
            esc_attr($this->get_watermark_styles($options)),
            esc_url($options['image_url'])
        );

        return $content . $watermark_html;
    }

    /**
     * Get watermark styles based on options.
     *
     * @param array $options Watermark options.
     * @return string CSS styles for the watermark.
     */
    private function get_watermark_styles(array $options): string {
        $styles = [
            'position: fixed',
            'pointer-events: none',
            'z-index: 9999',
            sprintf('opacity: %s', esc_attr($options['opacity'] ?? '0.5')),
        ];

        if (isset($options['position'])) {
            $styles[] = $this->get_position_styles($options['position']);
        }

        return implode(';', $styles);
    }

    /**
     * Get position styles for watermark.
     *
     * @param string $position Position of the watermark.
     * @return string CSS styles for positioning.
     */
    private function get_position_styles(string $position): string {
        switch ($position) {
            case 'top-left':
                return 'top: 0; left: 0;';
            case 'top-right':
                return 'top: 0; right: 0;';
            case 'bottom-left':
                return 'bottom: 0; left: 0;';
            case 'bottom-right':
                return 'bottom: 0; right: 0;';
            case 'center':
                return 'top: 50%; left: 50%; transform: translate(-50%, -50%);';
            default:
                return '';
        }
    }
}

