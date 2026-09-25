<?php

namespace Jtargosz\ActionOtp\Support;

enum OtpStatus: string
{
    case Sent = 'sent';
    case Matched = 'matched';
    case Verified = 'verified';
    case Empty = 'empty';
    case Mismatch = 'mismatch';
    case Throttled = 'throttled';
    case Expired = 'expired';
}
