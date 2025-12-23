<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Template Service
 * 
 * Manages reusable content templates
 */
class TemplateService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Create template
     */
    public function createTemplate(int $userId, string $name, string $content, ?array $variables = null): int
    {
        return $this->db->insert(
            "INSERT INTO content_templates (user_id, name, content, variables)
             VALUES (?, ?, ?, ?)",
            [$userId, $name, $content, $variables ? json_encode($variables) : null]
        );
    }

    /**
     * Get template
     */
    public function getTemplate(int $templateId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM content_templates WHERE id = ?",
            [$templateId]
        );
    }

    /**
     * Get user templates
     */
    public function getUserTemplates(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM content_templates WHERE user_id = ? ORDER BY name",
            [$userId]
        );
    }

    /**
     * Apply template with variables
     */
    public function applyTemplate(int $templateId, array $values): string
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template) {
            return '';
        }

        $content = $template['content'];
        
        // Replace variables like {{name}}, {{price}}, etc.
        foreach ($values as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Update template
     */
    public function updateTemplate(int $templateId, array $data): void
    {
        $sets = [];
        $values = [];

        foreach (['name', 'content', 'variables'] as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                if ($field === 'variables' && is_array($value)) {
                    $value = json_encode($value);
                }
                $sets[] = "$field = ?";
                $values[] = $value;
            }
        }

        if (!empty($sets)) {
            $values[] = $templateId;
            $this->db->execute(
                "UPDATE content_templates SET " . implode(', ', $sets) . " WHERE id = ?",
                $values
            );
        }
    }

    /**
     * Delete template
     */
    public function deleteTemplate(int $templateId): void
    {
        $this->db->execute("DELETE FROM content_templates WHERE id = ?", [$templateId]);
    }
}
