<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PesanEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $namaPengirim;
    public string $isiPesan;

    public function __construct(string $namaPengirim, string $subjekEmail, string $isiPesan)
    {
        $this->namaPengirim = $namaPengirim;
        $this->isiPesan = $isiPesan;
        $this->subjekEmail = $subjekEmail;
    }

    public string $subjekEmail;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjekEmail,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pesan',
            with: [
                'namaPengirim' => $this->namaPengirim,
                'isiPesan' => $this->isiPesan,
            ],
        );
    }
}
