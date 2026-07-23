USE sietelsa;

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS telefono VARCHAR(30) NULL AFTER email,
  ADD COLUMN IF NOT EXISTS direccion VARCHAR(200) NULL AFTER telefono,
  ADD COLUMN IF NOT EXISTS foto_path VARCHAR(255) NULL AFTER direccion;

CREATE TABLE IF NOT EXISTS usuario_permiso (
  usuario_id INT UNSIGNED NOT NULL,
  permiso_id INT UNSIGNED NOT NULL,
  permitido TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (usuario_id, permiso_id),
  CONSTRAINT fk_usuario_permiso_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_usuario_permiso_permiso
    FOREIGN KEY (permiso_id) REFERENCES permisos(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
