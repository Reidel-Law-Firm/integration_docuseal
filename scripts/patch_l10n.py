import json, collections
EN_ADD = {
    'Campi configurati': 'Fields configured',
    'Caricamento editor...': 'Loading editor...',
    'Configura campi del documento': 'Configure document fields',
    'Configura campi firma': 'Configure signature fields',
    'Configurazione campi salvata!': 'Field configuration saved!',
    'Connection successful': 'Connection successful',
    'Date': 'Date',
    'DocuSeal is not configured. Please contact your administrator.': 'DocuSeal is not configured. Please contact your administrator.',
    "Dopo l'invio si aprirà la finestra di firma direttamente qui, senza uscire da Nextcloud.": 'After sending, the signing window will open directly here, without leaving Nextcloud.',
    "Errore nel caricamento dell'editor": 'Error loading editor',
    "Firma subito dopo l'invio (se sei tra i firmatari)": 'Sign right after sending (if you are one of the signers)',
    'Impossibile aprire la firma embedded: ': 'Could not open embedded signing: ',
    'Indietro': 'Back',
    'Modifica campi': 'Edit fields',
    'Nessun URL di firma disponibile per il tuo utente': 'No signing URL available for your user',
    'Please sign the attached document.': 'Please sign the attached document.',
    'Request signature with DocuSeal': 'Request signature with DocuSeal',
    'Salva e continua': 'Save and continue',
    'Signature': 'Signature',
    'Signature request': 'Signature request',
    'Trascina e posiziona firma, data, testo e altri campi sul documento': 'Drag and place signature, date, text and other fields onto the document',
    'https://docuseal.example.com': 'https://docuseal.example.com',
}
IT_ADD = {
    'Campi configurati': 'Campi configurati',
    'Caricamento editor...': 'Caricamento editor...',
    'Configura campi del documento': 'Configura campi del documento',
    'Configura campi firma': 'Configura campi firma',
    'Configurazione campi salvata!': 'Configurazione campi salvata!',
    'Connection successful': 'Connessione riuscita',
    'Date': 'Data',
    'DocuSeal is not configured. Please contact your administrator.': "DocuSeal non è configurato. Contatta l'amministratore.",
    "Dopo l'invio si aprirà la finestra di firma direttamente qui, senza uscire da Nextcloud.": "Dopo l'invio si aprirà la finestra di firma direttamente qui, senza uscire da Nextcloud.",
    "Errore nel caricamento dell'editor": "Errore nel caricamento dell'editor",
    "Firma subito dopo l'invio (se sei tra i firmatari)": "Firma subito dopo l'invio (se sei tra i firmatari)",
    'Impossibile aprire la firma embedded: ': 'Impossibile aprire la firma embedded: ',
    'Indietro': 'Indietro',
    'Modifica campi': 'Modifica campi',
    'Nessun URL di firma disponibile per il tuo utente': 'Nessun URL di firma disponibile per il tuo utente',
    'Please sign the attached document.': 'Si prega di firmare il documento allegato.',
    'Request signature with DocuSeal': 'Richiedi firma con DocuSeal',
    'Salva e continua': 'Salva e continua',
    'Signature': 'Firma',
    'Signature request': 'Richiesta di firma',
    'Trascina e posiziona firma, data, testo e altri campi sul documento': 'Trascina e posiziona firma, data, testo e altri campi sul documento',
    'https://docuseal.example.com': 'https://docuseal.example.com',
}

def patch(path, additions):
    # Use object_pairs_hook to preserve order while loading
    with open(path, encoding='utf-8') as f:
        data = json.load(f, object_pairs_hook=collections.OrderedDict)
    tr = data['translations']
    added = 0
    for k, v in additions.items():
        if k not in tr:
            tr[k] = v
            added += 1
    with open(path, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=4)
        f.write('\n')
    print(f'{path}: added {added} entries')

patch('l10n/en.json', EN_ADD)
patch('l10n/it.json', IT_ADD)
