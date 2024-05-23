import {FC, useState} from 'react';
import {Button, ButtonProps} from '@calvient/decal';

interface ConfirmableBtnProps extends ButtonProps {
  confirmText?: string;
  onConfirm: () => void;
}

const ConfirmableBtn: FC<ConfirmableBtnProps> = ({
  confirmText = 'Are you sure?',
  onConfirm,
  children,
  ...rest
}) => {
  const [needsToBeConfirmed, setNeedsToBeConfirmed] = useState(false);

  const handleClick = () => {
    if (needsToBeConfirmed) {
      onConfirm();
      setNeedsToBeConfirmed(false);
    } else {
      setNeedsToBeConfirmed(true);
    }
  };

  return (
    <Button onClick={handleClick} {...rest}>
      {needsToBeConfirmed ? confirmText : children}
    </Button>
  );
};

export default ConfirmableBtn;
